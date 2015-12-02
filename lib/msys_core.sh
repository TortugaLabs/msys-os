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

<?php
require_once('msys_common.php');
if (!defined('BRIEF_OUTPUT')) {
  // Import macros...
  require_once('ashlib/core.sh');
  require_once('ashlib/fixfile.sh');
  require_once('ashlib/fixlnk.sh');
  require_once('ashlib/fixattr.sh');
  require_once('ashlib/network.sh');
  if (!defined('NO_SHLOG')) {
    echo fixfile_inc('ashlib/shlog','/bin/shlog',['mode'=>755]);
  }
} else {
  echo ('#'.NL);
  echo ('# .. .. Suppressing macros .. ..'.NL);
  echo ('# ... use -DTEST_SHOW_ALL=1 to include ...'.NL);
  echo ('#'.NL);
}
require_once('instree.php');
if (!defined('BRIEF_OUTPUT')) require_once('instree.sh');
?>
