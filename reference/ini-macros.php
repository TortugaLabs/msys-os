<?php
function ipcf() {
  $args = func_get_args();
  $ifname = array_shift($args);

  $res = [];

  foreach ($args as $n) {
    $n = trim($n);
    if (preg_match('/^net:/',$n)) {
      $r = explode('.',preg_replace('/^net:/','',$n));
      $res['node'] = array_pop($r);
      $res['net'] = implode('.',$r);
      continue;
    }
    $r = explode('=',$n,2);
    if (count($r) == 2) {
      $res[trim($r[0])] = trim($r[1]);
      continue;
    }
    if (preg_match('/^[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]$/', $n)) {
       $res['mac'] = $n;
       continue;
    }
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/',$n)) {
      $res['ipv4'] = $n;
      continue;
    }
    if (filter_var($n,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) {
      $res['ipv6'] = $n;
      continue;
    }
    $res[$n] = 'true';
  }
  // Complete entries...
  if (!isset($res['noipv4'])) {
    if (!isset($res['ipv4']) && isset($res['net']) && isset($res['node']) && ((int)$res['node'])) {
      $res['ipv4'] = '$<nets.'.$res['net'].'.ipv4>.'.$res['node'];
    }
  }
  if (!isset($res['noipv6'])) {
    if (!isset($res['ipv6']) && isset($res['net']) && isset($res['node']) && ((int)$res['node'])) {
      $res['ipv6'] = '$<nets.'.$res['net'].'.ipv6>::'.$res['node'];
    }
  }
  // Output entries...
  foreach ($res as $i=>$j) {
    echo $ifname.'.'.$i.' = '.$j.PHP_EOL;
  }
  if (isset($res['ipv6'])) {
    echo 'ipv6[] = '.$res['ipv6'].PHP_EOL;
    echo 'ip[] = '.$res['ipv6'].PHP_EOL;
  }
  if (isset($res['ipv4'])) {
    echo 'ipv4[] = '.$res['ipv4'].PHP_EOL;
    echo 'ip[] = '.$res['ipv4'].PHP_EOL;
  }
}

//require_once('msys-os/phplib/readcfg.php');
//require_once('msys-os/phplib/vlookup.php');
//$cf = read_ini('cfg/globs.ini');
//echo vlookup('namek0', $cf).PHP_EOL;
//echo vlookup('namek1', $cf).PHP_EOL;
//echo sh_export($cf,NULL,'_CF_');
//print_r($cf);

