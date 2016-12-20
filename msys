#!/bin/bash
#
# SECRETS_CFG
# ADM_KEYS
# MSYS_TEMPLATE_PATH
# MSYS_INI

[ -z "$MSYS_BASE" ] && export MSYS_BASE=$(cd $(dirname $0) && pwd)
export PATH=$PATH:"$MSYS_BASE"
eval $($MSYS_BASE/ashlib/ashlib)
ASHCC=$ASHLIB/ashcc
RXX=$MSYS_BASE/rxx

type $ASHCC >/dev/null 2>&1 || fatal "No ASHCC found"
op=msys_main
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

msys_main() {
  local ashcc_args=() rxx_args=()
  local \
    ssh_host=false \
    archive_log=true \
    archive_file="" \
    archive_dir="" \
    verbose=false

  while [ $# -gt 0 ] ; do
    case "$1" in
      -v)
        verbose=true
	;;
      --archive-dir=*)
        archive_dir="${1#--archive-dir=}"
	shift
	;;
      --no-archive|-N)
	archive_log=false
	archive_file=""
	shift
	;;
      --archive-file)
	archive_log=true
	archive_file=""
	shift
	;;
      --archive-file=*)
	archive_log=true
	archive_file="${1#--archive-file=}"
	shift
	;;
      --show|-t)
	rxx_args+=( "$1" )
	ashcc_args+=( "-DTEST_MODE=1" )
	archive_log=false
	archive_file=""
	;;
      --ssh=*|--cmd=*|--local|-l|--sudo=*|--sudo|--no-log|--log|--extra=*|--remote-user=*|--ruser=*|--no-agent|--id=*|--ssh-proxy=*)
	rxx_args+=( "$1" )
        ;;
      -x)
	rxx_args+=( "$1" "$2" )
	shift
	;;
      -I*|-e*|-D*)
	ashcc_args+=( "$1" )s
	;;
      --ssh)
	ssh_host=true
	;;
      *)
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
  trap "rm -f $temp_script" EXIT

  $verbose && warn "Preparing script..."
  if ! $ASHCC -o"$temp_script" "${ashcc_args[@]}" "-DMSYS_NAME='$msys_host'" "$MSYS_BASE/msys_main.php" "$@" ; then
    fatal "ASHCC execution failed"
  fi
  RCODE=$(
    set - $(tail -1 "$temp_script")
    [ x"$1" = x"#SUCCESS" ] && exit 0
    shift
    echo "$*"
    exit 1
  ) || fatal "$RCODE"
  #
  # If we need to save it, create archive file
  #
  if $archive_log ; then
    if [ -z "$archive_file" ] ; then
      [ -z "$archive_dir" ] && archive_dir="$(pwd)/archives"
      [ ! -d $archive_dir ] && mkdir -p $archive_dir
      n=0
      archive_file="$(archive_filename $archive_dir $n $msys_host)"
      while [ -f $archive_file ]
      do
	n=$(expr $n + 1)
	archive_file="$(archive_filename $archive_dir $n $msys_host)"
      done
    fi
    $verbose && warn "Archiving to $archive_file"
    cat "$temp_script" > "$archive_file"
  fi
    
  # send thru rxx
  $verbose && warn "Running RXX"
  rxx "${rxx_args[@]}" "$temp_script"
}

msys_secrets() {
  exec $MSYS_HOME/msecrets "$@"
}

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
    --msecrets)
      op=msys_secrets
      ;;
    --help)
      op=msys_help
      ;;
    *)
      break
  esac
  shift
done

"$op" "${args[@]}" "$@"


exit

######################################################################
######################################################################
######################################################################
######################################################################

include ver
MSYS_VER=$(gitver $MSYS_HOME)
MSYS_DIR=$(cd $(dirname $0) && pwd)

ASHCC=ashcc

IDFILE=
DISABLE_AGENT=no

OP=USAGE
SSH_ADDR=""
LCMD=""
EXTRAS=(
  "-DMSYS_HOME=\"$MSYS_HOME\""
  "-DMSYSDIR=\"$MSYS_DIR\""
  "-DVERSION=\"$MSYS_VER\""
  "-I$MSYS_HOME/pkgs"
  "-I$MSYS_DIR/lib"
  "-I$MSYS_HOME"
)
ARCHIVE_LOG=yes
ARCHIVE_FILE=


if [ "$OP" = "USAGE" ] ; then
  sed s/^#// <<'EOF'
#++
# = MSYS(1)
# :Revision: 2.0
# :man manual: msys operations manual
# :Author: A Liu Ly
#
# == NAME
#
# msys - system configuration script
#
# == SYNOPSIS
#
# *msys op* _arguments_ _sysname|template_
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
# *-t*::
#    Test mode.  Will only show config script in `stdout`.  Output
#    may be sumarized.
# *--ssh* [=ip_address]::
#    Will configure host through *ssh(1)* command.  By default
#    IPP address will be obtained through nslookup.  This can
#    be overriden by specifying an IP address.
# *--cmd=* cmd::
#    Will feed the configuration script to the specified `cmd`.
# *--local|-l*::
#    Will configure localhost by feeding the script to `/bin/sh`.
#
# == OPTIONS
#
# *--no-archive*::
#    Disable the creation of an archive file.
# *--archive-file* [=<file>]::
#    Enable the creation of an archive file.  If `file` is
#    specified, archive will be saved in that file location.
# *-D|-I|-e*::
#    These options are passed to "ashcc" verbatim.
# *--no-agent*::
#    Disable the use of the SSH authentication agent.
# *--id=* file::
#    Specified the SSH key to use.
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
#
# == SEE ALSO
#
# ashcc(1), ssh(1)
#--
EOF
  exit
fi

[ "$#" -ne 1 ] && fatal "Usage: msys [opts] host|template"

#
# Create the config script
#
TARGET_HOST="$1"
shift
TMP_FILE=$(mktemp)
trap "rm -f $TMP_FILE" EXIT

$ASHCC -o$TMP_FILE \
    "${EXTRAS[@]}" \
    -DSYSNAME=\"$TARGET_HOST\" \
    -DMSYS_OP=\"$OP\" \
    $MSYS_DIR/msys_main.php



######################################################################
#
# OP's to perform
#
######################################################################

cmd() {
  $LCMD
}

do_ssh() {
  [ $DISABLE_AGENT = yes ] && export SSH_AUTH_SOCK=
  [ -z "$SSH_ADDR" ] && SSH_ADDR="$TARGET_HOST"

  local xopts=()
  if [ -n "$SSH_PROXY_COMMAND" ] ; then
    xopts+=(-o ProxyCommand="$SSH_PROXY_COMMAND")
  fi

  if [ $ARCHIVE_LOG = no ] ; then
    ssh -T -l root $IDFILE $SSH_EXTRA_OPTS "${xopts[@]}" "$SSH_ADDR"
  else
    ssh -T -l root $IDFILE $SSH_EXTRA_OPTS "${xopts[@]}" "$SSH_ADDR" \
	sh -c ': ; export PATH=$PATH:/usr/local/bin ; type shlog && exec shlog || exec sh'
  fi
}

######################################################################

$OP < $TMP_FILE
