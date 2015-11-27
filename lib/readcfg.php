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


/**
 * read config directory
 *
 * Reads all the files with '.ini' extensions.  Directories with '.d'
 * extensions are also read and aggregated.
 *
 * @param string $dir	directory to read
 * @return array	multi-dimension array structure with config settings
 */
function read_cfgd($dir) {
  if ($dh = opendir($dir)) {
    $cfg = array();
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
 * Process include statements
 *
 * If the `ini` file contains statements like:
 *
 *    include[] = filename
 *
 * Filename will be include within the section.
 *
 * @internal
 * @param array_ref $ini	INI structure to examine
 * @param string $dir		Directory for includes
 */
function _do_includes(&$ini,$dir) {
  if (isset($ini['include'])) {
    if (!is_array($ini['include'])) {
      $ini['include'] = array($ini['include']);
    }
    foreach ($ini['include'] as $inc) {
      if (!is_file($inc) && is_file("$dir/$inc")) {
	$inc = "$dir/$inc";
      }
      $ink = parse_ini_file($inc,true);
      if ($ink !== false) {
	_do_includes($ink,dirname($inc));
	foreach ($ink as $k => &$v) {
	  $ini[$k] = $v;
	}
      }
    }
    unset($ini['include']);
  }
  foreach ($ini as $k => &$v) {
    if (!is_array($v)) continue;
    _do_includes($v,$dir);
  }
}

/**
 * Read a ini config file and apply our extensions
 *
 * Extensions:
 *
 *    - `key = [ value, value ]` 
 *       these are converted to arrays
 *    - `key.key.key = value`  
 *    - `[a.b]`  
 *      The dots are used to split values into arrays.
 *    - `includes[] = file`  
 *       Files are included
 * 
 * @param string $file	File to read
 * @return array	multi-dimension array structure with config settings
 */
function read_ini($file) {
  $ini = parse_ini_file($file,true);
  if ($ini === false) return false;
  _do_includes($ini,dirname($file));

  // Post process stuff...
  _ini_expand_arrays($ini);
  _ini_expand_keys($ini);
  return $ini;
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
  foreach ($ini as $k => &$v) {
    if (strpos($k,'.') !== false) {
      // Found '.' separators...
      $ptr = &$ini;
      foreach (explode('.',$k) as $j) {
	if (!isset($ptr[$j])) {
	  $ptr[$j] = array();
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
