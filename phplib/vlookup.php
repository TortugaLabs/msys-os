<?php
/**
 * vlookup - module to lookup variables in config array
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
 * @package	vlookup
 * @license     http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Lookups up a value in a $ini stash, it also performs variable
 * expansions ($abc or $<abc>).  Or PHP <?= statement ?>
 * 
 * @param str $var	variable to lookup
 * @param array $cf	INI stash
 * @param array $opts	Lookup options
 * @param array $v	(internal) used for recursive lookups
 * @param array $cvar	(internal) used for error reporting in recursive lookups
 * @return mixed	found value or $default.
 */
define('VLOOKUP_DEFAULT', '$default$');
define('VLOOKUP_FLATTEN', '$flatten$');
define('VLOOKUP_FLATTEN_NO_UNDEF', '$flatten_no_undef$');
define('VLOOKUP_UNDEF_ERROR', '$rlookup_error$');
define('VLOOKUP_UNDEF_MARK', '$rlookup_markit$');
define('VLOOKUP_UNDEF_SILENT', '$rlookup_silent$');
//define('VLOOKUP_NO_PHP_EXPANSION', '$rlookup_nophp$');


function vlookup($var, $cf, $opts = NULL, $v = NULL,$cvar=NULL) {
  if ($opts == NULL) $opts = [ VLOOKUP_DEFAULT => NULL ];
  if (!array_key_exists(VLOOKUP_DEFAULT,$opts)) $opts[VLOOKUP_DEFAULT] = NULL;
  foreach ([
      VLOOKUP_FLATTEN => ',' ,
      VLOOKUP_FLATTEN_NO_UNDEF => TRUE ,
      VLOOKUP_UNDEF_ERROR => TRUE,
      VLOOKUP_UNDEF_MARK => TRUE,
      VLOOKUP_UNDEF_SILENT => TRUE,
      //VLOOKUP_NO_PHP_EXPANSION => TRUE,
    ] as $k => $j) {
    if (array_key_exists($k,$opts)) continue;
    $vv = array_search($k, $opts,TRUE);
    if ($vv === FALSE) {
      $opts[$k] = FALSE;
    } else {
      $opts[$k] = $j;
      unset($opts[$vv]);
    }
  }
  if ($v === NULL) $v = &$cf;
  if ($cvar == NULL) $cvar = $var;
  if (strpos($var,'.') !== FALSE) {
    list($i,$j) = explode('.',$var,2);
    if (!isset($v[$i])) return $opts[VLOOKUP_DEFAULT];
    return vlookup($j, $cf, $opts, $v[$i],$cvar);
  }
  if (!isset($v[$var])) return $opts[VLOOKUP_DEFAULT];
  
  // Handle arrays
  if (is_array($v[$var])) {
    $out = [];
    $oo = $opts;
    if ($opts[VLOOKUP_FLATTEN_NO_UNDEF]) {
      $oo[VLOOKUP_FLATTEN_NO_UNDEF] = FALSE;
      $oo[VLOOKUP_DEFAULT] = NULL;
      $oo[VLOOKUP_UNDEF_ERROR] = TRUE;
    }
    foreach (array_keys($v[$var]) as $k) {
      $j = vlookup($k,$cf, $oo, $v[$var]);
      if ($opts[VLOOKUP_FLATTEN_NO_UNDEF] && $j === NULL) continue;
      $out[$k] = $j;
    }
    if ($opts[VLOOKUP_FLATTEN]) return implode($opts[VLOOKUP_FLATTEN], $out);
    return $out;
  }
  
  /*
  // Check if there are PHP variables
  if (!$opts[VLOOKUP_NO_PHP_EXPANSION] && strpos('<?=',$v[$var]) !== FALSE) {
    ob_start();
    try {
      if (eval('?>'.$v[$var]) === FALSE) throw new Exception('parse error');
    } catch (Exception $e) {
      fwrite(STDERR,'PHP Parse error in "$'.$cvar.'": '.$e->getMessage().PHP_EOL);
      ob_end_clean();
      return $opts[VLOOKUP_DEFAULT];
    }
    $txt = ob_get_contents();
    ob_end_clean();
    return $txt;
  }
  */

  if (strpos($v[$var],'$') === FALSE) return $v[$var]; // Trivial case... no variable referenced
  // Handle variable expansions
  $out = '';
  $off = 0;
  while (preg_match('/\$([_A-Za-z0-9\.]+|\<[_A-Za-z0-9\.]+>)/', $v[$var], $mv, PREG_OFFSET_CAPTURE, $off)) {
    $out .= substr($v[$var], $off, $mv[0][1] - $off);
    $off = $mv[0][1]+strlen($mv[0][0]);
    $i = $mv[1][0];
    if (substr($i,0,1) == '<') $i = substr($i,1,strlen($i)-2);
    
    $oo =  [
	VLOOKUP_DEFAULT => NULL,
	VLOOKUP_FLATTEN => TRUE,
	VLOOKUP_UNDEF_ERROR => $opts[VLOOKUP_UNDEF_ERROR],
	VLOOKUP_UNDEF_MARK => $opts[VLOOKUP_UNDEF_MARK],
	VLOOKUP_UNDEF_SILENT => $opts[VLOOKUP_UNDEF_SILENT],
      ];
    $j = vlookup($i, $cf, $oo, $v);
    if ($j == NULL) $j = vlookup($i, $cf, $oo);
    if ($j == NULL) {
      if (!$opts[VLOOKUP_UNDEF_SILENT]) fwrite(STDERR,'Undefined variable "$'.$i.'" (used by "$'.$cvar.'")'.PHP_EOL);
      if ($opts[VLOOKUP_UNDEF_ERROR]) return $opts[VLOOKUP_DEFAULT];
      $j = $opts[VLOOKUP_UNDEF_MARK] ? "<UNDEFINED:$i>" : $mv[0][0];
    }
    $out .= $j;
  }
  $out .= substr($v[$var],$off);
  return $out;
}

/**
 * Determine if an array is a vector (or list)
 * 
 * @param array $vec
 * @return bool
 */
function is_vecEx(array &$vec, $chk = FALSE) {
  # Determine if $vec array is a vector
  #-
  $lst = array_keys($vec);
  $cnt = count($lst);
  for ($i = 0; $i < $cnt ; $i++) {
    if ($i !== $lst[$i]) return FALSE;
    if ($chk && is_array($vec[$i])) return FALSE;
  }
  return TRUE;
}

/**
 * Export configuration hive as Shell script variables
 * 
 * @param array $vec
 * @return bool
 */
function sh_export($cf,$opts = NULL, $prefix= '',$v = NULL,$indent='') {
  $out = '';
  if ($v === NULL) $v = &$cf;
  foreach ($v as $i=>$j) {
    if (strpos($i,'$') !== FALSE) continue; // Skip keys that contain '$'
    $ii = _vlookup_shvar($i);
    if (is_array($j)) {
      if (is_vecEx($j,TRUE)) {
	$out .= $indent.'# v:'.$i.PHP_EOL;
	$oo = $opts;
	$oo[VLOOKUP_FLATTEN] = ' ';
	$oo[VLOOKUP_FLATTEN_NO_UNDEF] = TRUE;
	$out .= $indent.$prefix.$ii.'='.escapeshellarg(vlookup($i,$cf,$oo,$v)).PHP_EOL;
      } else {
	$out .= $indent.'# a:'.$i.PHP_EOL;
	$keys = []; $names = [];
	foreach (array_keys($j) as $k) {
	  if (strpos($k,'$') !== FALSE) continue; // Skip keys that contain '$'
	  $kk = _vlookup_shvar($k);
	  $keys[] = $prefix.$ii.'_'.$kk;
	  $out .= $indent.$prefix.$ii.'_'.$kk.'__NAME_='.escapeshellarg($k).PHP_EOL;
	  $names[] = $k;
	}
	if (count($k) == 0) continue;
	$out .= $indent.$prefix.$ii.'__NAMES_='.escapeshellarg(implode(' ',$names)).PHP_EOL;
	$out .= $indent.$prefix.$ii.'='.escapeshellarg(implode(' ',$keys)).PHP_EOL;
	$out .= sh_export($cf,$opts,$prefix.$ii.'_',$j,$indent.' ');
      }
      $out .= $indent.'####'.PHP_EOL;
    } else {
      $out .= $indent.$prefix.$ii.'='.escapeshellarg(vlookup($i, $cf, $opts, $v)).PHP_EOL;
    }
  }
  return $out;
}

/**
 *
 * Sanitise shell variable names
 * @internal
 * @param string $varname	Input name
 * @return string		Sanitised output name
 */
function _vlookup_shvar($varname) {
  return strtr(strtoupper($varname),'-. */:','___x__');
}



