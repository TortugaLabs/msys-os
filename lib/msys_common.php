<?php
//
// Generic MSYS stuff
//
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
