<?php
function std_check() {
  if (!defined('ASHLIB')) // Make sure dependancies are loaded!
    die("ERROR: Must include MSYS_BASE.'phplib/macros.php and std_init(cf,home)\n");
}

function std_init($cf,$home = FALSE) {
  $version = '$Testing$';
  if ($home && is_dir($home)) {
    $r = exec('git -C '.escapeshellarg($home).' describe');
    if ($r) $version = $r;
  }
  define('VERSION',$version); 
  define('ASHLIB',MSYS_BASE.'ashlib/');
  
  if (defined('BRIEF_OUTPUT')) {
    echo '# Summarized test output'.PHP_EOL;
    echo 'echo use -DTEST_SHOW_ALL=1 to show full version'.PHP_EOL;
    echo '#'.PHP_EOL;
  } else {
    // Import macros...
    require_once(ASHLIB.'core.sh');
    require_once(ASHLIB.'fixfile.sh');
    require_once(ASHLIB.'fixlnk.sh');
    require_once(ASHLIB.'fixattr.sh');
    require_once(ASHLIB.'network.sh');
  }
  echo 'cat >/etc/msys.info <<EOF'.PHP_EOL;
  echo 'MSYS_SYSNAME="'.MSYS_NAME.'"'.PHP_EOL;
  echo 'MSYS_VERSION="'.$version.'"'.PHP_EOL;
  echo 'MSYS_HOME="'.MSYS_HOME.'"'.PHP_EOL;
  echo 'MSYS_CFGHOST='.php_uname('n').PHP_EOL;
  echo 'MSYS_DATE="'.date('Y-m-d H:i').'"'.PHP_EOL;
  echo 'EOF'.PHP_EOL;
  echo '. /etc/msys.info'.PHP_EOL;

  if (!defined('BRIEF_OUTPUT')) {
    echo sh_export($cf,NULL,'CF_');
    echo sh_export($cf,NULL,'SYS_', $cf['hosts'][MSYS_NAME]);
  }
}

define('STD_COPYFILE_PHPMODE','$std_copyfile_phpmode$');
define('STD_COPYFILE_QUOTED','$std_copyfile_quoted$');

function std_copyfile($src,$dst,$opts = []) {
  $flags = [];
  foreach ([STD_COPYFILE_PHPMODE,STD_COPYFILE_QUOTED] as $f) {
    if (array_key_exists($f,$opts)) {
      $flags[$f] = $opts[$f];
      unset($opts[$f]);
    } elseif (($k = array_search($f,$opts,TRUE)) !== FALSE) {
      unset($opts[$k]);
      $flags[$f] = TRUE;
    } else {
      $flags[$f] = FALSE;
    }
  }
  $out = PHP_EOL;
  $out .= 'fixfile';
  foreach ($opts as $i=>$j) {
    $out .= ' --'.$i.'='.$j;
  }
  $out .= ' '.$dst.' <<';
  $out .= $flags[STD_COPYFILE_QUOTED] ? QEOFMARK : EOFMARK;
  $out .= PHP_EOL;
  
  if (defined('BRIEF_OUTPUT')) {
    $out .= '# BRIEF_OUTPUT...'.PHP_EOL;
  } else {
    if ($flags[STD_COPYFILE_PHPMODE]) {
      ob_start();
      include($src);
      $out .= ob_get_clean();
    } else {
      $out .= file_get_contents($src);
    }
  }
  $out .= EOFLINE;
  return $out;
}
