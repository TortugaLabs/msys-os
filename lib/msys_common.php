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

function fixfile_inc($src,$dst,$opts=null) {
   // Make globals variable in this context...
   foreach ($GLOBALS as $i=>$j) {
      eval('global $'.$i.';');
   }
   $txt = NL.'fixfile';
   if (is_array($opts)) {
      foreach ($opts as $i=>$j) {
	 if (is_numeric($i)) {
	    $txt .= ' --'.$j;
	 } else {
	    $txt .= ' --'.$i.'='.$j;
	 }
      }
   }
   $txt .= ' '.$dst.' <<'.QEOFMARK.NL;

   ob_start();
   require($src);
   $txt .= ob_get_clean();

   $txt .= EOFLINE;
   return $txt;
}
