<?php
//
// Generic MSYS stuff
//
require_once('msys_lib.php');
//require_once('readcfg.php');
function ipcf() {
   $args = func_get_args();
   $res = [];
   foreach ($args as $n) {
      if (preg_match('/^net:/',$n)) {
	 $res['ip'] = $n;
	 continue;
      }
      if (preg_match('/^[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]$/', $n)) {
	 $res['mac'] = $n;
	 continue;
      }
      if (preg_match('/^([_a-zA-Z]+)=(.*)$/',$n,$mv)) {
	 $res[$mv[1]] =  $mv[2];
	 continue;
      }
      $res[$n] = true;
   }
   return $res;
}

require_once('lookup.php');


require_once('vars/globs.php');
require_once('vars/nets.php');
require_once('vars/hosts.php');

$cf = [
   'globs' => &$globs,
   'nets' => &$nets,
   'hosts' => &$hosts,
];
if (!isset($cf['hosts'][SYSNAME])) {
  trigger_error('No definition for system "'.SYSNAME.'" if loaded config',
		E_USER_ERROR);
}
