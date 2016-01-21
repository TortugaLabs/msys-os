#!/bin/sh
#
<?php
  if (!defined('BRIEF_OUTPUT')) {
    // Import macros...
    require_once('ashlib/core.sh');
    require_once('ashlib/fixfile.sh');
    require_once('ashlib/fixlnk.sh');
    require_once('ashlib/fixattr.sh');
  } else {
    ?>
    #
    # .. .. Suppressing macros .. ..
    #
    <?php
  }
?>

sysid=$(uname -n)
<?php if (is_file(MSYS_SECRETS_CFG)) { ?>
secrets=/etc/secrets.cfg
fixfile --mode=600 $secrets <<EOF
<?php readfile(MSYS_SECRETS_CFG); ?>

EOF
echo MD5a:$sysid:$(md5sum $secrets)

<?php } ?>

<?php if (is_file(MSYS_ADMIN_KEYS)) { ?>
if [ -d /etc/dropbear ] ; then
  authkeys=/etc/dropbear/authorized_keys
else
  authkeys=$HOME/.ssh/authorized_keys
  mkdir -p $(dirname $authkeys)
fi

syskeys=/etc/msys.keys
if [ -f $syskeys ] ; then
  old=$(md5sum $syskeys | awk '{print $1}')
else
  old="___"
fi

fixfile --mode=600 $syskeys <<EOF
<?php readfile(MSYS_ADMIN_KEYS); ?>

EOF
new=$(md5sum $syskeys | awk '{print $1}')

echo MD5b:$sysid:$(md5sum $syskeys)

[ "$new" = "$old" ] && exit

if [ -f $authkeys ] ; then
  cur=$(md5sum $authkeys | awk '{print $1}')
  #echo "cur=$cur old=$old"
  if [ x"$cur" != x"$old" ] ;then
    warn "$authkeys does not use $syskeys"
    exit
  fi
  warn "Updating $authkeys..."
else
  warn "Initializing $authkeys..."
fi
cp -a $syskeys $authkeys

<?php } ?>
