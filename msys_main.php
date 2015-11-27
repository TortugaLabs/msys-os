<?php
/**
 * msys_main - this is the main template generation function of msys
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

if (defined('DEBUG')) {
  echo('set -x'.NL);
}
if (defined('TEST_MODE') && !defined('TEST_SHOW_ALL')) {
  /**
   * Used to test if running with the `-t` option to skip _un-interesting_
   * code segment templates.
   */
  define('BRIEF_OUTPUT',true);
}

/**
 * Used to have somewhat unique EOF marker
 */
define('EOFMARK','EOF--cuthere--DEADBEEFC010--cuthere--EOF');
/**
 * Adds single quotes to EOFMARK
 */
define('QEOFMARK',"'".EOFMARK."'");
/**
 * The actual EOFLINE for shell HERE documents
 */
define('EOFLINE',NL.EOFMARK.NL);

date_default_timezone_set('UTC');

//////////////////////////////////////////////////////////////////////

/**
 * Searches for template files
 *
 * Searches template directories for `$name`.  It will check extensions '.php',
 * and '.sh' before no extensions.  If found the
 * full path to that file is returned.
 *
 * Otherwise returns `false`.
 *
 * @param $name string	file to lookup
 * @return string	returns full path or false.
 */
function find_templ($name) {
  foreach (array_merge(glob(MSYS_HOME.'/templ/*',GLOB_MARK|GLOB_ONLYDIR),
							  glob(MSYS_HOME.'/pkgs/*/templ',GLOB_MARK|GLOB_ONLYDIR),
							  glob(MSYSDIR.'/templ',GLOB_MARK|GLOB_ONLYDIR))
			  as $tpldir) {
    foreach (array('.php','.sh','') as $ext) {
      if (is_file($tpldir.$name.$ext)) {
	return $tpldir.$name.$ext;
      }
    }
  }
  return false;
}

/**
 * Register some text to be executed towards the end of the script
 *
 * @param $txt string	string to schedule
 */
function post_code($txt) {
  global $msys_post_code_data;
  $msys_post_code_data .= $txt;
}
/**
 * Register some text to be shown towards the end of the script
 *
 * Note that this is show after `post_code` text is executed.
 *
 * @param $txt string	string to schedule
 */
function post_text($txt) {
  global $msys_post_code_text;
  $msys_post_code_text .= $txt;
}

//////////////////////////////////////////////////////////////////////

$templ = find_templ(SYSNAME);
if ($templ === false) {
  trigger_error('Missing template '.SYSNAME,E_USER_ERROR);
  die('Aborting!');
}

define('MSYS_TEMPL',$templ);
define('MSYS_TEMPL_DIR',dirname($templ).'/');
set_include_path(get_include_path().PATH_SEPARATOR.dirname($templ));
$msys_post_code_data = 'echo ("# post code data\n");'.NL;
$msys_post_code_text = '# post code text'.NL;

require($templ);
eval($msys_post_code_data);
echo($msys_post_code_text);

echo(NL.'#SUCCESS'.NL);
