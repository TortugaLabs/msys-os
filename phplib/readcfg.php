<?php
/**
 * readcfg - module to read configuration data
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @package	readcfg
 * @license     http://www.opensource.org/licenses/bsd-license.php
 */
if (!defined('MAX_LINE')) define('MAX_LINE', 4096);
define('READ_INI_NO_PREPROCESSOR',	1);
define('READ_INI_NO_PHP_MACROS',	1<<1);
define('READ_INI_NO_TEMPLATES',		1<<2);
define('READ_INI_NO_INLINE_ARRAYS',	1<<3);
define('READ_INI_NO_EXPAND_KEYS',	1<<4);

define('READ_INI_SCANNER_SHIFT',	6);
define('READ_INI_SCANNER_NORMAL',	0);
define('READ_INI_SCANNER_RAW',		1<<READ_INI_SCANNER_SHIFT);
define('READ_INI_SCANNER_TYPED',	2<<READ_INI_SCANNER_SHIFT);

/**
 * Read a ini config file and apply our extensions
 *
 * Extensions:
 *
 *    - $include file
 *       Includes the given `file`
 *    - $include prefix=file
 *       Includes the given `file` with all sections prefixed with 
 *       `prefix`.
 *    - $php command line
 *       Executes the given PHP line
 *    - `key = [ value, value ]` 
 *       these are converted to arrays
 *    - `key.key.key = value`  
 *    - `[a.b]`  
 *      The dots are used to split values into arrays.
 *    - `[something : base]`
 *      use `base` as a template for `something.
 * 
 * @param string $file	File to read
 * @return array	multi-dimension array structure with config settings
 */
function read_ini($file, $flags = 0) {
  if (($flags & READ_INI_NO_PREPROCESSOR) == 0) {
    if (($flags & READ_INI_NO_PHP_MACROS) == 0) {
      $inc = get_include_path();
      set_include_path($inc.PATH_SEPARATOR.dirname(realpath($file)));
      $txt = _preprocess_ini($file,FALSE);
      set_include_path($inc);
    } else {
      $txt = _preprocess_ini($file,TRUE);
    }
  } else {
    $txt = file_get_contents($file);
  }
  if ($txt == '') return FALSE;

  switch ($flags >> READ_INI_SCANNER_SHIFT) {
    case READ_INI_SCANNER_RAW:
      $scanner = INI_SCANNER_RAW;
      break;
    case READ_INI_SCANNER_TYPED:
      $scanner = PHP_VERSION_ID < 50601 ? INI_SCANNER_NORMAL : INI_SCANNER_TYPED;
      break;
    default:
      $scanner = INI_SCANNER_NORMAL;
  }

  $ini = parse_ini_string($txt,TRUE,$scanner);
  if ($ini === FALSE) return FALSE;
  if (isset($ini['@'])) {
    foreach ($ini['@'] as $i => $j) {
      $ini[$i] = $j;
    }
    unset($ini['@']);
  }
  
  // Post process stuff...
  if (($flags & READ_INI_NO_TEMPLATES) == 0) _ini_expand_templates($ini);
  if (($flags & READ_INI_NO_INLINE_ARRAYS) == 0) _ini_expand_arrays($ini);
  if (($flags & READ_INI_NO_EXPAND_KEYS) == 0) _ini_expand_keys($ini);
  return $ini;
}


/**
 * read config directory
 *
 * Reads all the files with '.ini' extensions.  Directories with '.d'
 * extensions are also read and aggregated.
 *
 * @param string $dir	directory to read
 * @return array	multi-dimension array structure with config settings
 */
function read_cfgdir($dir) {
  if ($dh = opendir($dir)) {
    $cfg = [];
    while (($fn = readdir($dh)) !== false) {
      if ($fn == '.' || $fn == '..') continue;
      if (is_dir("$dir/$fn") && substr($fn,-2,2) == '.d') {
	$key = substr($fn,0,-2);
	$dat = read_cfgd("$dir/$fn");
      } elseif (is_file("$dir/$fn") && substr($fn,-4,4) == '.ini') {
	$key = substr($fn,0,-4);
	$dat = read_ini("$dir/$fn");
      } else {
	// Anything else is ignored...
	continue;
      }
      if (isset($cfg[$key])) {
	// Merge keys...
	foreach ($dat as $k => &$v) {
	  $cfg[$key][$k] = &$v;
	}
      } else {
	$cfg[$key] = $dat;
      }
    }
    closedir($dh);
    return $cfg;
  } else {
    return NULL;
  }
}

/**
 * Parses INI file adding extends functionality via ":base" postfix on namespace.
 *
 * @internal
 * @param arrayref $ini		config settings array
 */
function _ini_expand_templates(&$ini) {
  $p_ini = [];

  foreach($ini as $namespace => $properties){
    if (!is_array($properties) || strpos($namespace,':') == FALSE) continue;        
    list($name, $base) = explode(':', $namespace,2);

    $name = trim($name);
    $base = trim($base);
    
    if (!isset($ini[$base])) {
      $i = explode('.',$name);
      if (count($i) > 1) {
	array_pop($i);
	$i[] = $base;
	$base = implode('.',$i);
      }
      if (!isset($ini[$base])) continue;
    }
    $p_ini[$namespace] = [ $name,$base ];
  } 
  foreach ($p_ini as $namespace => $i) {
    list($name,$base) = $i;
    if (!isset($ini[$name])) $ini[$name] = [];
    
    // Inherit base namespace
    foreach ($ini[$base] as $j=>$k) {
      $ini[$name][$j] = $k;
    }
    // Overwrite / set current namespace values
    foreach ($ini[$namespace] as $j=>$k) {
      $ini[$name][$j] = $k;
    }
    unset($ini[$namespace]);
  }

}

/**
 * Expand arrays `[ value, value, value ]`
 *
 * @internal
 * @param &$ini	array_ref	array to examine
 */
function _ini_expand_arrays(&$ini) {
  foreach ($ini as $k => &$v) {
    if (is_array($v)) {
      _ini_expand_arrays($v);
      continue;
    }
    if (substr($v,0,1) == '[' && substr($v,-1,1) == ']') {
      $v = trim(substr($v,1,-1));
      $v = preg_split('/\s*,\s*/',$v);
      continue;
    }
  }
}

/**
 * Parse ini keys and values and handle extensions
 *
 * Passed array is modified.
 *
 * @internal
 * @param arrayref $ini		config settings array
 */

function _ini_expand_keys(&$ini) {
  ksort($ini);
  foreach ($ini as $k => &$v) {
    if (strpos($k,'.') !== false) {
      // Found '.' separators...
      $ptr = &$ini;
      foreach (explode('.',$k) as $j) {
	if (!isset($ptr[$j])) {
	  $ptr[$j] = [];
	}
	$ptr = &$ptr[$j];
      }
      $ptr = $v;
      unset($ini[$k]);
    }
  }
  foreach ($ini as $k => &$v) {
    if (is_array($v)) {
      _ini_expand_keys($v);
      continue;
    }
  }
}

/**
 * Read INI file and processing $include and any macro
 * directives
 *
 * @internal
 * @param str $file	Filename to read
 * @paramm bool $nophp	Disable PHP macros
 * @param str $meta	Prepends the meta tag to the sections in the file
 * @return str	Post processed output
 */
function _preprocess_ini($file, $nophp = FALSE, $meta = '', $msg = NULL) {
  if ($msg == NULL) {
    $fp = fopen($file,'r');
    if ($fp === FALSE) return '';
  } else {
    $fp = @fopen($file,'r');
    if ($fp == FALSE) {
      fwrite(STDERR,implode(',',$msg).": Error including \"$file\"\n");
      return '';
    }
  }
  $txt = '';
  $ln = 0;
  while (FALSE !== ($ln = fgets($fp, MAX_LINE))) {
    ++$ln;
    if (preg_match('/^\s*\$include\s+([^\n]+)/', $ln, $mv)) {
      $mv = preg_split('/\s*=\s*/',$mv[1],2);
      if (count($mv) == 1) {
	$inc = $mv[0];
	$inmeta = '';
      } else {
	list($inmeta,$inc) = $mv;
      }
      $inc = dirname($file).'/'.$inc;
      $txt .= '[@]'.PHP_EOL;
      $txt .= _preprocess_ini($inc, $nophp, $inmeta, [$file,$ln]);
    } else if (!$nophp && preg_match('/^\s*\$php\s+([^\n]+)/', $ln, $mv)) {
      ob_start();
      try {
	if (eval($mv[1].';') === FALSE) throw new Exception('parse error');
      } catch (Exception $e) {
	fwrite(STDERR,implode(',',[$file,$ln]).': PHP Parse error, '.$e->getMessage().PHP_EOL);
      }
      $txt .= ob_get_contents().PHP_EOL;
      ob_end_clean();
    } else if ($meta != '' && preg_match('/^\s*\[/',$ln, $mv)) {
      $off = strlen($mv[0]);
      $txt .= substr($ln,0,$off).$meta.'.'.substr($ln,$off);
    } else {
      $txt .= $ln;
    }
  }
  if (substr($txt,-1) != "\n") $txt.= "\n";
  return $txt;
}


