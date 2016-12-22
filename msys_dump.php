<?php
/**
 * msys_dump - dump configuration data
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
 * @package	msys
 * @license     http://www.opensource.org/licenses/bsd-license.php
 */

if (getenv('MSYS_BASE')) define('MSYS_BASE',preg_replace('/\/+$/','',getenv('MSYS_BASE')).'/');
require_once(MSYS_BASE.'phplib/msys_common.php');

function txt_mode($cf,$argv) {
  if (count($argv) == 0) {
    print_r($cf);
    return;
  }
  foreach ($argv as $var) {
    $dat = qlookup($var,$cf);
    echo "$var: ";
    if ($dat === NULL) {
      global $rv;
      ++$rv;
      echo "NOT FOUND\n";
      continue;
    }
    print_r($dat);
  }
}

function php_mode($cf,$argv) {
  if (count($argv) == 0) {
    echo serialize($cf);
    return;
  }
  $out = [];
  foreach ($argv as $var) {
    $dat = vlookup($var,$cf);
    $out[$var] = $dat;
    if ($dat === NULL) {
      global $rv;
      ++$rv;
    }
  }
  echo serialize($out);
}

function sh_mode($cf,$argv) {
  if (count($argv) == 0) {
    echo sh_export($cf);
    return;
  }
  foreach ($argv as $var) {
    $dat  = qlookup($var,$cf);
    if ($dat === NULL) {
      echo "not_found $var\n";
    } else {
      echo sh_export($cf,NULL,'',$dat);
    }
  }
}

function query_mode($cf,$argv) {
  if (count($argv) == 0) {
    echo implode(' ',array_keys($cf)).PHP_EOL;
    return;
  }
  $prefix = (count($argv) > 1) ? $var.' :' : "";
  foreach ($argv as $var) {
    $dat  = vlookup($var,$cf,[
	      VLOOKUP_DEFAULT => '#not_found#',
		VLOOKUP_FLATTEN,
	      VLOOKUP_FLATTEN_NO_UNDEF]);
    echo $prefix.$dat."\n";
  } 
}

function main($argv) {
  if (count($argv) < 1) die("Must specify a dump mode\n");
  $mode = array_shift($argv);

  $cf = msys_init();

  switch ($mode) {
    case 'txt':
      txt_mode($cf,$argv);
      break;
    case 'sh':
      sh_mode($cf,$argv);
      break;
    case 'php':
      php_mode($cf,$argv);
      break;
    case 'query':
      query_mode($cf,$argv);
      break;
  }
}

$rv = 0;
main($argv);
exit($rv);
