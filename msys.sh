#!/bin/bash
#

[ -z "${MSYS_BASE:-}" ] && export MSYS_BASE=$(cd $(dirname $0) && pwd)
export PATH=$PATH:"$MSYS_BASE"
eval $($MSYS_BASE/ashlib/ashlib)
ASHCC=$ASHLIB/ashcc
RXX=$MSYS_BASE/rxx.sh

type $ASHCC >/dev/null 2>&1 || fatal "No ASHCC found"
op=(msys_main)
args=()

usage() {
  cat <<-EOF
	Usage: $0 [options] system [template-options]
	EOF
  exit
}

archive_filename() {
  local dir="$1" n="$2" sysname="$3"
  echo -n $dir/msys-$sysname
  echo .$(date '+%Y%m%d').$n.txt
}

pre_processor() {
  local temp_script="$1" ; shift
  (
    $ASHCC -o"$temp_script" "$@" || fatal "ASHCC execution failed"
    #cat "$temp_script"
    RCODE=$(
        ln="$(tail -1 "$temp_script")"
	if [ -z "$ln" ] ; then
	  echo 'FAILURE'
	else
	  set - $ln
	  [ x"$1" = x"#SUCCESS" ] && exit 0
	  echo "$ln"
	fi
	exit 1
      ) || fatal "$RCODE"
    return 0
  ) && return 0
  return 1
}


is_ashcc_arg() {
  local r_list="$1" r_shift="$2"
  shift 2
  case "$1" in
    -I*|-e*|-D*)
      eval ${r_list}'+=( "$1" )'
      eval ${r_shift}=1
      return 0
      ;;
  esac
  return 1
}
is_rxx_arg() {
  local r_list="$1" r_shift="$2"
  shift 2
  case "$1" in
    --ssh=*|--cmd=*|--local|-l|--sudo=*|--sudo|--no-log|--log|--extra=*|--remote-user=*|--ruser=*|--no-agent|--id=*|--ssh-proxy=*|--show|-t)
      eval ${r_list}'+=( "$1" )'
      eval ${r_shift}=1
      return 0
      ;;
    -x)
      eval ${r_list}'+=( "$1" "$2" )'
      eval ${r_shift}=2
      return 0
      ;;
  esac
  return 1
}

######################################################################

msys_help() {
  sed s/^#// <<'EOF'
#++
# = MSYS(1)
# :Revision: 3.0
# :man manual: msys operations manual
# :Author: A Liu Ly
#
# == NAME
#
# msys - system configuration script
#
# == SYNOPSIS
#
# *msys* _global-opts_ *op* _arguments_ _sysname|template_
#
#
# == DESCRIPTION
#
# *msys* is a configuration management utility.  It is meant to be
# very ad-hoc orientated.
#
# It can either be used to configure systems or to run special *msys*
# operations as defined by templates.
#
# == MODES
#
# *--show|-t*::
#    Test mode.  Will only show config script in `stdout`.  Output
#    may be sumarized.
# *--ssh* [=ip_address]::
#    Will configure host through *ssh(1)* command.  By default
#    IP address will be obtained through nslookup.  This can
#    be overriden by specifying an IP address.
# *--cmd=cmd::
#    Will feed the configuration script to the specified `cmd`.
# *--sudo* [=cmd]::
#    Will run locally using `sudo(1)`.
# *--local|-l*::
#    Will configure localhost by feeding the script to `/bin/sh`.
# *--secret*::
#    Send secret configuration files to target system.
# *--help*::
#    This help file.
#
# == GLOBAL OPTIONS
#
# *--secrets=path*:
#    Specify the `secrets` configuration file to use.
# *--admkeys=path*::
#    Specify the `authorized_keys` file to use.
# *--ini=path*::
#    Path to the main configuration file
# *--template-path=*::
#    Path used to look-up templates
#
# == MAIN OPTIONS
#
# *--no-archive*::
#    Disable the creation of an archive file.
# *--archive-file* [=<file>]::
#    Enable the creation of an archive file.  If `file` is
#    specified, archive will be saved in that file location.
# *--archive-dir=path*::
#    Enable the creation of archive files and keep them in `path`.
# *-D*`const=value`::
#    This option is passed to "ashcc" verbatim.
#    Defines `const` as a PHP constant with value `value`.  This is
#    evaluated as PHP so if you need to define a string you must
#    provide the quotes.
# *-I*`include-dir`::
#    This option is passed to "ashcc" verbatim.
#    Adds the specified directory to the include search path.
# *-e*`php_code`::
#    This option is passed to "ashcc" verbatim.
#    Evaluates the given PHP code.
# *--extra=*`stuff` | *-x* `stuff`::
#    Add additional arguments to `rxx` (transport) command.
# *--remote-user=*`user` | *--ruser=*`user`::
#    Remote user to use when executing config scripts.
# *--no-agent*::
#    Disable the use of the SSH authentication agent.
# *--id=* file::
#    Specified the SSH key to use.
# *--ssh-proxy=*`cmd`::
#    Specifies a SSH proxy command.  For example:
#	`ssh -W %h:%p <user>@<jumpserver>`
#
#
# == CONSTANTS
#
# *--DDEBUG=1*::
#    Enable -x for the executing shell.
# *--DTEST_SHOW_ALL=1*::
#    Disables BRIEF_OUTPUT in test mode.
# *--DBRIEF_OUTPUT=1*::
#    Enabled automatically by `-t` (test mode).  Templates can use it
#    to suppress output of uninteresting boilerplate code.
#
# == ENVIRONMENT
#
# The following variables are recognized:
#
# *SSH_EXTRA_OPTS*::
#    Additional options to use when calling `ssh`.
# *SSH_PROXY_COMMAND*::
#    Used to add a ProxyCommand directive for jump tunneling.
#    Example proxy command:
#
#	`ssh -W %h:%p <user>@<jumpserver>`
# SECRETS_CFG
# ADM_KEYS
# MSYS_TEMPLATE_PATH
# MSYS_INI
# MSYS_BASE
#
# == SEE ALSO
#
# ashcc(1), ssh(1)
#--
EOF
  exit
}

msys_main() {
  local ashcc_args=() rxx_args=()
  local \
    ssh_host=false \
    archive_log=true \
    archive_file=""

  while [ $# -gt 0 ] ; do
    case "$1" in
      --archive-dir=*)
        ARCHIVE_DIR="${1#--archive-dir=}"
	archive_log=true
	archive_file=""
	;;
      --no-archive|-N)
	archive_log=false
	archive_file=""
	;;
      --archive-file)
	archive_log=true
	archive_file=""
	;;
      --archive-file=*)
	archive_log=true
	archive_file="${1#--archive-file=}"
	;;
      --show|-t)
	rxx_args+=( "$1" )
	ashcc_args+=( "-DTEST_MODE=1" )
	archive_log=false
	archive_file=""
	;;
      --ssh)
	ssh_host=true
	;;
      *)
	if is_ashcc_arg ashcc_args shift "$@" ; then
	  shift $shift
	  continue
	elif is_rxx_arg rxx_args shift "$@" ; then
	  shift $shift
	  continue
	fi
	break
    esac
    shift
  done
  [ $# -eq 0 ] && usage
  # Handle multi host? in parallel?
  local msys_host="$1"
  shift
  $ssh_host && rxx_args+=( "--ssh=$msys_host" )

  local temp_script="$(mktemp)"
  (
    pre_processor "$temp_script" "${ashcc_args[@]}" "-DMSYS_NAME='$msys_host'" "$MSYS_BASE/msys_main.php" "$@" || exit 1
    #
    # If we need to save it, create archive file
    #
    if $archive_log ; then
      if [ -z "$archive_file" ] ; then
	[ -z "$ARCHIVE_DIR" ] && ARCHIVE_DIR="$(pwd)/archives"
	[ ! -d $ARCHIVE_DR ] && mkdir -p $ARCHIVE_DIR
	n=0
	archive_file="$(archive_filename $ARCHIVE_DIR $n $msys_host)"
	while [ -f $archive_file ]
	do
	  n=$(expr $n + 1)
	  archive_file="$(archive_filename $ARCHIVE_DIR $n $msys_host)"
	done
      fi
      warn "Archiving to $archive_file"
      cat "$temp_script" > "$archive_file"
    fi
    
    # send thru rxx
    warn "Running $RXX"
    $RXX "${rxx_args[@]}" "$temp_script"
  )
  rv=$?
  rm -f "$temp_script"
  exit $rv
}

msys_secrets() {
  exec $MSYS_BASE/msecrets.sh "$@"
}

msys_dump() {
  local mode="$1" ; shift

  local ashcc_args=()
  while [ $# -gt 0 ] ; do
    is_ashcc_arg ashcc_args shift "$@" || break
    shift $shift
  done

  $ASHCC "${ashcc_args[@]}" "$MSYS_BASE/msys_dump.php" $mode "$@"
}


######################################################################

while [ $# -gt 0 ] ; do
  case "$1" in
    --secrets=*)
      export SECRETS_CFG="${1#--secrets=}"
      ;;
    --admkeys=*)
      export ADM_KEYS="${1#--admkeys=}"
      ;;
    --ini=*)
      export MSYS_INI="${1#--ini=}"
      ;;
    --template-path=*)
      export MSYS_TEMPLATE_PATH="${1#--template-path=}"
      ;;
    --dump)
      op=(msys_dump txt)
      ;;
    --dump=*)
      op=(msys_dump "${1#--dump=}")
      ;;
    --msecrets)
      op=(msys_secrets)
      ;;
    --help)
      op=(msys_help)
      ;;
    *)
      break
  esac
  shift
done

[ -z "${MSYS_INI:-}" ] && fatal "Configuration file not specified!"

"${op[@]}" "${args[@]}" "$@"


exit
