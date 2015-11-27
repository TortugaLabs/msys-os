#
# MSYS <?= VERSION.NL ?>
#
cat >/etc/msys.info <<EOF
MSYS_SYSNAME="<?=SYSNAME?>"
MSYS_VERSION="<?=VERSION?>"
MSYS_HOME="<?=MSYS_HOME?>"
MSYS_CFGHOST="<?=php_uname('n')?>"
MSYS_DATE="<?=date('Y-m-d H:i')?>"
EOF
. /etc/msys.info
