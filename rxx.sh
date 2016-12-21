#!/bin/bash
#
set -euf -o pipefail

op="usage"		# operation to execute
shlog="true"		# Use of `shlog` or equivalent when available
ruser="root"		# Remote user
target=""		# Depends on the transport...
extra_opts=()		# For additional command opts
ssh_proxy=""		# SSH Proxy command
idfile=""		# ssh key file
disable_agent="false"	# skip the use of `ssh-agent`

####################################################################
fatal() {
  echo "$@" 1>&2
  exit 1
}

handle_input() {
  if [ x"$*" = x"-" ] ; then
    cat
  else
    cat "$@"
  fi
}

chktarget() {
  [ -z "$target" ] && fatal "No CMD target specified"
}

parse_target() {
  [ $# -eq 0 ] && fatal "Must specify a target value"
  target="$1"
  shift
  extra_opts=( "$@" )  
}


show() {
  handle_input "$@"
}

do_sudo() {
  chktarget
  local cmd=( sudo -u "$ruser" "$target")
  $shlog && type shlog && cmd=( sudo -u "$ruser" shlog -c "$target" -- )
  handle_input "$@" |  "${cmd[@]}" "${extra_opts[@]}"
}
cmd() {
  chktarget
  local cmd=( "$target" )
  $shlog && type shlog && cmd=( shlog -c "$target" --)
  handle_input "$@" | "${cmd[@]}" "${extra_opts[@]}"
}
do_ssh() {
  chktarget
  $disable_agent && export SSH_AUTH_SOCK=
  local cmd=( ssh -T -l "$ruser" $idfile "${extra_opts[@]}" )
  [ -n "$ssh_proxy" ] && cmd+=( -o ProxyCommand="$ssh_proxy" )
  cmd+=( "$target" )
  $shlog && cmd+=(
	sh -c
	': ; export PATH=$PATH:/usr/local/bin ; type shlog && exec shlog || exec sh'
  )
  handle_input "$@" | "${cmd[@]}"
}

show_help() {
  sed -ne '/^#++$/,/^#--$/ p' "$0" | sed -e 's/^#$/# /' | grep '^#\s' | sed -e 's/^#\s//'
  exit
}
  
#++
# = RXX(1)
# :Revision: 1.0
# :man manual: msys operations manual
# :Author: A Liu Ly
#
# == NAME
#
# rxx - Remote Script Execution
#
# == SYNOPSIS
#
# *rxx op* _options_ _script_
usage() {
  fatal "Usage: $0 op [options] script(s)"
}
#
#
# == DESCRIPTION
#
# *rxx* is a remote script execution utility.  It is meant to be run
# from the *msys* configuration management utilty.
#

####################################################################

while [ $# -gt 0 ] ; do
  case "$1" in
# == MODES
#
# *--help|-h*::
#    Show command help.
    -h|--help)
      op="show_help"
      break
      ;;
# *--show|-t*::
#    Test mode.  Will only show script in `stdout`.
    -t|--show)
      op="show"
      shlog="false"
      ;;
# *--ssh=* target::
#    Will run script on host through *ssh(1)* command.
    --ssh=*)
      op="do_ssh"
      parse_target ${1#--ssh=}
      ;;
# *--cmd=* cmd::
#    Will feed the configuration script to the specified `cmd`.
    --cmd=*)
      op="cmd"
      parse_target ${1#--cmd=}
      ;;
# *--local|-l*::
#    Will configure localhost by feeding the script to `/bin/sh`.
    -l|--local)
      op="cmd"
      target="/bin/sh"
      ;;
# *--sudo=* cmd::
#    Will run script on localhost using *sudo(1)* command.
    --sudo=*)
      op="do_sudo"
      parse_target ${1#--cmd=}
      ;;
    --sudo)
      op="do_sudo"
      target="/bin/sh"
      ;;
#
# == OPTIONS
#
# *--no-log*::
#    Disable the creation of a log file
    --no-log|-N)
      shlog="false"
      ;;
# *--log*::
#    Use `shlog` if available to keep an archive of runs.
    --log)
      shlog="true"
      ;;
# *--extra=|-x* opt::
#    Add additional options to the target command.  While it is
#    possible to define these while specifying the target, this
#    is used to get around whitespace quoting issues.
    --extra=*)
      extra_opts+=( "${1#--extra=}" )
      ;;
    -x)
      [ -z "$2" ] && fatal "Must specify argument for -x"
      extra_opts+=( "$2" )
      shift
      ;;
# *--remote-user=|--ruser=* user::
#   User to run the command as (usually `root`)
    --remote-user=*)
      ruser="${1#--remote-user=}"
      ;;
    --ruser=*)
      ruser="${1#--ruser=}"
      ;;
#
# == SSH SPECIFIC OPTIONS
#
# *--no-agent*::
#    Disable the use of the SSH authentication agent.
    --no-agent)
      disable_agent="true"
      ;;
# *--id=* file::
#    Specified the SSH key to use.
    --id=*)
      disable_agent="true"
      idfile="-i ${1#--id=}"
      ;;
# *--ssh-proxy=* cmd::
#    Used to add a ProxyCommand directive for jump tunneling.
#    Example proxy command:
#
#	`ssh -W %h:%p <user>@<jumpserver>`
#
    --ssh-proxy=*)
      ssh_proxy=( -o ProxyCommand="${1#--ssh-proxy=}" )
      ;;
    *)
      break
      ;;
  esac
  shift
done
#
# == SEE ALSO
#
# ashcc(1), ssh(1), sudo(8)
#--


[ "$#" -eq 0 ] && usage

"$op" "$@"
