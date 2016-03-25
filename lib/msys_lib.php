<?php
/**
 * msyslib - Some utility functions for msys templates
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

/**
 * checks if $ip is of the form `net:`_netid_._hostid_
 *
 * @param $ip string	Address to verify
 * @return array($netid,$hostid)    The `netid` and `hostid` components otherwise (false,false)
 */
function parse_ipnet($ip) {
  if (substr($ip,0,4) == 'net:') {
    $pair = explode('.',substr($ip,4));
    if (count($pair) > 2) {
      trigger_error("Error parsing IP $ip\n",E_USER_WARNING);
      return array(false,false);
    }
    if (count($pair) == 1)
		 $pair[] = "";
	 else
		 $pair[1] = intval($pair[1]);
    return $pair;
  }
  return array(false,false);
}

/**
 * Check if an address is an IPv6 address
 *
 * @param $addr string	Possible IPv6 address
 * @return boolean
 */
function is_ipv6($addr) {
  return filter_var($addr,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6);

}

/**
 * Check if an address is an IPv4 address
 *
 * @param $addr string	Possible IPv4 address
 * @return boolean
 */
function is_ipv4($addr) {
  return filter_var($addr,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4);

}

/**
 * Check if a global is defined and returns it or a suitable default
 *
 * @param $var string	global variable to check
 * @param $def mixed	default value to use
 * @param $ext string	Used to specify sub elements in an array
 * @return mixed	Either the value or `$def`
 */
function ck($var,$def = '',$ext='') {
  eval('global '.$var.';');
  if (!eval('return isset('.$var.');')) return $def;
  if ($ext) {
    if (!eval('return isset('.$var.$ext.');')) return $def;
  }
  return eval('return '.$var.$ext.';');
}

/**
 * Conditional formatted string
 *
 * @param $var mixed	value to check|display
 * @param $fmt string	format to use in sprintf if $var is set
 * @param $def string	default value to use
 */
function cfmt($var,$fmt,$def = '') {
  if (isset($var)) return sprintf($fmt,$var);
  return $def;
}

/**
 * Used to include a file (honouring PHP macros) in the target configuration
 *
 * @param $src string	file to include
 * @param $dst string	path to file to create in target
 * @param $opts array	options to pass to fixfile
 */
function fixfile_inc($src,$dst,$opts=null) {
   if (defined('BRIEF_OUTPUT')) {
      return NL.'# fixfile include("'.$src.'" => "'.$dst.'") # BRIEF_OUTPUT'.NL;

   }
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
