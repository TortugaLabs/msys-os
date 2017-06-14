<?php
/*
 * CGI implementation
 */
set_include_path(get_include_path().PATH_SEPARATOR.dirname(realpath(__FILE__)));
require('WebMSys.php');

WebMSys::configure();
eval(WebMSys::export());

if (!isset($brief)) $brief = FALSE;
if (!isset($debug)) $debug = FALSE;

if (isset(WebMSys::$cf['main'])) {
  require('readcfg.php');
  require('vlookup.php');
  if (substr(WebMSys::$cf['main'],0,1) == 1) {
    WebMSys::$hive = read_ini(WebMSys::$cf['main']);
  } else {
    foreach ([
		realpath('.').'/',
		realpath(dirname(WebMSys::$cf_file)).'/',
		dirname(realpath(WebMSys::$cf_file)).'/',
	      ] as WebMSys::$i) {
      if (!is_file(WebMSys::$i.WebMSys::$cf['main'])) continue;
      if (!is_readable(WebMSys::$i.WebMSys::$cf['main'])) continue;
      WebMSys::$hive = read_ini(WebMSys::$i.WebMSys::$cf['main']);
      if (WebMSys::$hive) break;
    }
  }
  if (WebMSys::$hive === FALSE) WebMSys::fatal_error_handler('Missing Configuration INI hive');  
  $cf = expand_vars(WebMSys::$hive);
}
// URL must contain f=template entry
if (!isset($f)) $f = isset(WebMSys::$cf['default']) ? WebMSys::$cf['default'] : 'default';

header('Content-type: text/plain');
require(WebMSys::get_template($f));
