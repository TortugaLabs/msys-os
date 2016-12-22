#!/bin/sh
#
# Sample configuration file
#
<?php
  if (getenv('MSYS_HOME')) {
    define('MSYS_HOME',preg_replace('/\/+$/','',getenv('MSYS_HOME')).'/');
  } else {
    die("No MSYS_HOME defined\n");
  }
  require_once(MSYS_BASE.'phplib/macros.php');

  $cf = read_ini(MSYS_INI);
  std_init($cf,MSYS_HOME);
?>

#fixfile /etc/resolv.conf
fixfile /etc/resolv.conf <<EOF
domain $CF_DOMAIN
$(for ns in $CF_DNSD ; do echo nameserver $ns ; done)

EOF
