<?php
/*
 * Main Driver clases for the web based MSYS
 */
abstract class WebMSys {
  static public $i;
  static public $err;
  static public $cf;
  static public $template;
  static public $cf_file;
  static public $hive;
  
  static public function msys_base() {
    return dirname(realpath(__FILE__)).'/';
  }
  static public function parse_PATH_INFO($j) {
    $kv = explode('=',$j,2);
    if (count($kv) == 0) return FALSE;
    if (count($kv) == 1) $kv[1] = TRUE;
    // Sanity check...
    if (!preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/',$kv[0])) return FALSE;
    return $kv;
  }
  static public function fatal_error_handler($msg, $error = NULL) {
    ?>
    <html>
      <head>
	<title><?= $msg ?></title>
      </head>
      <body>
	<h1><?= $msg ?></h1>
	<?php
	  if ($error === NULL) {
	    echo 'Generic error';
	  } else if (is_a($error, 'Exception')) {
	    echo
		'Exception: '.get_class(self::$err).'<br/>'.
		'Message: '.self::$err->getMessage().'<br/>';
	  } else if (is_array($error)) {
	    echo 
		'Type: '.$error['type'].'<br/>'.
		'Message: '.$error['message'].'<br/>'.
		'File: '.$error['file'].'<br/>'.
		'Line: '.$error['line'].'<br/>';
	  } else {
	    echo 'Internal error';
	  }
	?>
      </body>
    </html>
    <?php
    exit(1);
  }
  static public function cfg_file($file) {
    self::$cf_file = $file;
    if (preg_match('/\.ini$/',$file)) {
      self::$cf = parse_ini_file($file,TRUE,
		PHP_VERSION_ID < 50601 ? INI_SCANNER_NORMAL : INI_SCANNER_TYPED);
      if (self::$cf === FALSE) self::fatal_error_handler('Configuration error in '.$file);
    } else if (preg_match('/\.phps?$/',$file)) {
      require($file);
    } else {
      self::fatal_error_handler('Internal config error for '.$file);
    }
  }
  static public function configure($fs = NULL, $is_httpd = TRUE,$process_one = TRUE) {
    if (!isset($fs) || $fs === NULL) {
      $fs = [];
    } else {
      if (!is_array($fs)) $fs = [$fs];
    }
    if ($is_httpd) array_push($fs,$_SERVER['SCRIPT_FILENAME']);
    
    foreach (['ini','phps','php'] as $ext) {
      foreach ($fs as $f) {
	foreach ([$f,realpath($f)] as $ff) {
	  $b = preg_replace('/\.php$/','',$ff);
	  $b = [
		$b.'-cfg.',
		$b.'.',
		dirname($f).'/config.',
	       ];
	  foreach ($b as $bb) {
	    if (is_readable($bb.$ext)) {
	      self::cfg_file($bb.$ext);
	      if ($process_one) return;
	      if ($ext == 'ini') break 3;
	    }
	  }
	}
      }
    }
  } /* End configure */
  static public function get_template($f,$fatal = TRUE) {
    if (!isset(self::$cf['templates'])) 
      self::fatal_error_handler('Missing templates configuration');
    if (!is_array(self::$cf['templates'])) self::$cf['templates'] = [self::$cf['templates']];

    $base_dirs = [
	realpath('.').'/',
	realpath(dirname(self::$cf_file)).'/',
	dirname(realpath(self::$cf_file)).'/',
    ];
    
    foreach (self::$cf['templates'] as $d) {
      if (substr($d,0,1) == '/') {
	$sel = [ '' ];
      } else {
	$sel = $base_dirs;
      }
      foreach ($sel as $bb) {
	if (is_readable($bb.$d.'/'.$f)) {
	  self::$template = $bb.preg_replace('/\/*$/','/',$d).$f;
	  return self::$template;
	}
      }
    }
    if ($fatal) self::fatal_error_handler('Missing template: '.$f);
    return FALSE;
  }
  static public function export($key = 'globals') {
    $txt = '';
    if (!isset(self::$cf[$key])) return '';
    if (!is_array(self::$cf[$key])) return '';

    $kn = addslashes($key);
    foreach (self::$cf[$key] as $k => $v) {
      if (!preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/',$k)) continue;
      $txt .= 'if (!isset($'.$k.')) $'.$k.' = '. self::class . '::$cf["'.$kn.'"]["'.$k.'"];'.PHP_EOL;
    }
    return $txt;
  }
}

//
// This is done here so that any variables defined are in the global
// scope
// 
if (isset($_SERVER['PATH_INFO'])) {
  foreach (explode('/',$_SERVER['PATH_INFO']) as WebMSys::$i) {
    WebMSys::$i = WebMSys::parse_PATH_INFO(WebMSys::$i);
    if (WebMSys::$i === FALSE) continue;
    try {
      WebMSys::$i = eval('$'.WebMSys::$i[0].' = WebMSys::$i[1];');
      if (WebMSys::$i === FALSE) WebMSys::fatal_error_handler('WebMSys EVAL error',error_get_last());
    } catch (Exception $err) {
      WebMSys::fatal_error_handler('WebMSys EVAL exception',$err);
    }
  }
}



