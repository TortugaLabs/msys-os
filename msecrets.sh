#!/bin/sh
#
#++
# = SECRETS(1)
# :man manual: msys operations manual
# :Revision: 1.0
# :Author: A Liu Ly
#
# == NAME
#
# secrets - upload secrets
#
# == SYNOPSIS
#
# *msecrets* _[--secrets=file] [--admkeys=keys]_ [-t|--test]_ _system_
#
# == DESCRIPTION
#
# This script uploads secret files to target systems.  It is
# done outside the normal *msys* config process in order to avoid
# logging the payload.  By default *msys* will log configuration scripts.
#
# == OPTIONS
#
# *--secrets=* file::
#    Secrets configuration file.  Defaults to `/etc/secrets.cfg`.
# *--admkeys=* keys::
#    Admin ssh keys.  Defaults to `/etc/msys.keys`.
# *-t|--test*
#    Test mode.
#
# == ENVIRONMENT
#
# *SECRETS_CFG*:: Path to secrets config file.
# *ADM_KEYS*:: Path to admin keys file.
#
# == SEE ALSO
#
# msys(8)
#
#--
[ -z "$SECRETS_CFG" ] && SECRETS_CFG=/etc/secrets.cfg
[ -z "$ADM_KEYS" ] && ADM_KEYS=/etc/msys.keys

script_dir=$(cd $(dirname $0) && pwd)
adm='root'
test=false

while [ $# -gt 0 ] ; do
  case "$1" in
    -t|--test)
      test=true
      shift
      ;;
    --secrets=*)
      SECRET_CFG=${1#--secrets=}
      shift
      ;;
    --admkeys=*)
      ADM_KEYS=${1#--admkeys=}
      shift
      ;;
    *)
      break
      ;;
  esac
done

eval $("$script_dir"/ashlib/ashlib)

(
  echo '#!/bin/sh'
  for s in core.sh fixfile.sh
  do
    cat "$ASHLIB/$s"
  done
  if [ -f "$SECRETS_CFG" ] ; then
    echo 'secrets=/etc/secrets.cfg'
    echo 'fixfile --mode=600 "$secrets" <<EOF'
    cat "$SECRETS_CFG"
    echo ''
    echo 'EOF'
    echo 'echo "MD5a:$(uname -n):$(md5sum "$secrets")"'
  fi
  if [ -f "$ADM_KEYS" ] ; then
    echo 'syskeys=/etc/msys.keys'    
    function x_init() {
      if [ -d /etc/dropbear ] ; then
	authkeys=/etc/dropbear/authorized_keys
      else
	authkeys=$HOME/.ssh/authorized_keys
	mkdir -p $(dirname $authkeys)
      fi
      if [ -f "$syskeys" ] ; then
	old=$(md5sum "$syskeys" | awk '{print $1}')
      else
	old="xxx"
      fi
    }
    declare -f x_init
    echo 'x_init'
    echo 'fixfile --mode=600 "$syskeys" <<EOF'
    cat "$ADM_KEYS"
    echo ''
    echo 'EOF'
    echo 'echo "MD5b:$(uname -n):$(md5sum "$syskeys")"'
    function x_post() {
      new=$(md5sum "$syskeys" | awk '{print $1}')
      [ $new = $old ] && return
      
      if [ -f "$authkeys" ] ; then
	new=$(md5sum "$authkeys" | awk '{print $1}')
	if [ $cur = $old ] ; then
	  warn "$authkeys is not based on $syskeys"
	  return
	fi
	warn "Updating $authkeys..."
      else
	warn "Initializing $authkeys..."
      fi
      cp -a "$syskeys" "$authkeys"
    }
    declare -f x_post
    echo 'x_post'
  fi
) | (
  $test && exec cat

  script="$(cat)"
  
  for ip in "$@"
  do
     echo "$script" | ssh -l "$adm" "$@"
  done
)



