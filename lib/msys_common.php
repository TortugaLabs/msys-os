<?php
//
// Generic MSYS stuff
//
require_once('msys_core.sh');
require_once('msys_lib.php');

require_once('readcfg.php');
require_once('lookup.php');

$cf = read_cfgd(CFGDIR);
if (is_null($cf)) {
  trigger_error('Unable to read config file',E_USER_ERROR);
}
if (!isset($cf['hosts'][SYSNAME])) {
  trigger_error('No definition for system "'.SYSNAME.'" if loaded config',
		E_USER_ERROR);
}

if (!defined('BRIEF_OUTPUT')) {
  // Import macros...
  require_once('ashlib/core.sh');
  require_once('ashlib/fixfile.sh');
  require_once('ashlib/fixlnk.sh');
  require_once('ashlib/fixattr.sh');
  require_once('ashlib/network.sh');
} else {
  echo ('#'.NL);
  echo ('# .. .. Suppressing macros .. ..'.NL);
  echo ('# ... use -DTEST_SHOW_ALL=1 to include ...'.NL);
  echo ('#'.NL);
}
require_once('instree.php');
