<?php
require_once('msys_lib.php');

//define('MSYS_LOOKUP_FIRST',-27651);

function _add_alias($name,&$dat,&$aliases) {
  if (isset($aliases[$name])) {
    if (!isset($aliases[$name][$dat['hostname']])) {
      $aliases[$name][$dat['hostname']] = $dat;
    } // else -- this alias already points to this hostname
  } else {
    $aliases[$name] = array($dat['hostname'] => $dat);
  }
}

function lookup($name = NULL) {
  global $cf, $aliases;
  if (!isset($aliases)) {
    $aliases = array();
    foreach ($cf['hosts'] as $hn => &$dat) {
      $dat['hostname'] = $hn;
      _add_alias($hn,$dat,$aliases);
      if (isset($dat['alias'])) {
	foreach ($dat['alias'] as $cn) {
	  _add_alias($cn,$dat,$aliases);
	}
      }
    }
  }
  if (isset($name)) {
    if (isset($aliases[$name])) return $aliases[$name];
  }
  return NULL;
}

/*

function lookup_nic($a,$b = NULL) {
  global $cf;
  if (isset($b)) {
    $host = $a;
    $nic = $b;
  } else {
    $host = SYSNAME;
    $nic = $b;
  }

  if (!isset($cf['hosts'][$host])) return NULL;
  if (!isset($cf['hosts'][$host]['nics'])) return NULL;
  if (!isset($cf['hosts'][$host]['nics'][$nic])) return NULL;
  return $cf['hosts'][$host]['nics'][$nic];
}

function lookup_host($host = NULL) {
 global $cf;
 if (!isset($host)) $host = SYSNAME;
 if (!isset($cf['hosts'][$host])) return NULL;
 return $cf['hosts'][$host];
}
*/
function _lk_defaults($a,$b) {
  global $aliases;
  if (!isset($aliases)) lookup();

  if (isset($a) && isset($b)) {
    $host = $a;
    $nic = $b;
  } elseif (isset($a) && !isset($b)) {
    if (isset($aliases[$a])) {
      $host = $a;
      $nic = NULL;
    } else {
      $host = SYSNAME;
      $nic = $a;
    }
  } elseif (!isset($a) && isset($b)) {
    $host = SYSNAME;
    $nic = $b;
  } else { // !isset($a) && !isset($b)
    $host = SYSNAME;
    $nic = NULL;
  }

  return array($host,$nic);
}

/**
 * Parse and lookup an IP address reference
 *
 * Given an ip in the form `net:`_netid_._hostid_, it will generate
 * IPv4 and/or IPv6 addresses for it.
 *
 * @param $ip	string		IP address reference
 * @param &$cf	array_ref	Configuration array, must have a `nets` element
 * @return array		Array of strings containing IPv4/IPv6 addresses
 */
function lk_parse_ip($ip,&$cf) {
  list($netid,$hostid)=  parse_ipnet($ip);
  $res = array();
  if ($netid) {
    if (!isset($cf['nets'][$netid])) {
      trigger_error("Unknown network $ip",E_USER_WARNING);
      return $res;
    }
    $res['netid'] = $netid;
    $netdat = &$cf['nets'][$netid];
    if (isset($netdat['vlan'])) $res['vlan'] = $netdat['vlan'];
    $res['hostid'] = $hostid;
    if (!$hostid) return $res;
    // Configured without IP...
    if (isset($netdat['ip6'])) $res['ip6']=$netdat['ip6'].'::'.dechex($hostid);
    if (isset($netdat['ip4'])) $res['ip4']=$netdat['ip4'].'.'.$hostid;
  } else {
    if (is_ipv6($ip)) {
      $res['ip6'] = $ip;
    } elseif (is_ipv4($ip)) {
      $res['ip4'] = $ip;
    }
  }
  return $res;
}

function lk_addr($name = NULL,$nic = NULL) {
  if (!isset($name)) $name = SYSNAME;
  $res = array();
  global $cf;

  if (isset($nic)) {
    // a NIC is specified
    if (!isset($cf['hosts'][$name])) return $res;
    if (!isset($cf['hosts'][$name]['nics'])) return $res;
    if (!isset($cf['hosts'][$name]['nics'][$nic])) return $res;
    if (!isset($cf['hosts'][$name]['nics'][$nic]['ip'])) {
      if (isset($cf['hosts'][$name]['nics'][$nic]['mac'])) {
	$cf['hosts'][$name]['nics'][$nic]['ip']
	  = array('mac' => $cf['hosts'][$name]['nics'][$nic]['mac']);
	$res[] = $cf['hosts'][$name]['nics'][$nic]['ip'];
      }
      return $res;
    }
    if (is_array($cf['hosts'][$name]['nics'][$nic]['ip'])) {
      $res[] = $cf['hosts'][$name]['nics'][$nic]['ip'];
      return $res;
    }
    $cf['hosts'][$name]['nics'][$nic]['ip']
      = lk_parse_ip($cf['hosts'][$name]['nics'][$nic]['ip'],$cf);
    $cf['hosts'][$name]['nics'][$nic]['ip']['hostname'] = $name;
    $cf['hosts'][$name]['nics'][$nic]['ip']['logical']
      = $name.'-'.$nic;
    if (isset($cf['hosts'][$name]['nics'][$nic]['mac']))
    $cf['hosts'][$name]['nics'][$nic]['ip']['mac']
      = $cf['hosts'][$name]['nics'][$nic]['mac'];

    $res[] = $cf['hosts'][$name]['nics'][$nic]['ip'];
    return $res;
  }
  $dats = lookup($name);
  if (!isset($dats)) return $res;
  foreach ($dats as $hn => &$hdat) {
    if (isset($hdat['ip'])) {
      if (!is_array($hdat['ip'])) {
	$hdat['ip'] = lk_parse_ip($hdat['ip'],$cf);
	$hdat['ip']['hostname'] = $hn;
	$hdat['ip']['logical'] = $hn;
	if (isset($hdat['mac'])) $hdat['ip']['mac'] = $hdat['mac'];
      }
      $res[] = $hdat['ip'];
    } elseif (isset($hdat['mac'])) {
      $hdat['ip'] = array('mac' => $hdat['mac']);
      $res[] = $hdat['ip'];
    }
    if (isset($hdat['nics'])) {
      foreach ($hdat['nics'] as $ifn => &$ifdat) {
	if (isset($ifdat['ip'])) {
	  if (!is_array($ifdat['ip'])) {
	    $ifdat['ip'] = lk_parse_ip($ifdat['ip'],$cf);
	    $ifdat['ip']['hostname'] = $hn;
	    $ifdat['ip']['logical'] = $hn.'-'.$ifn;
	    if (isset($ifdat['mac'])) $ifdat['ip']['mac'] = $ifdat['mac'];
	  }
	  $res[] = $ifdat['ip'];
	} elseif (isset($ifdat['mac'])) {
	  $ifdat['ip'] = array('mac' => $ifdat['mac']);
	  $res[] = $ifdat['ip'];
	}
      }
    }
  }
  return $res;
}



function lk_addr_item($type,$a = NULL,$b = NULL) {
  list($host,$nic) = _lk_defaults($a,$b);
  $addrtab = lk_addr($host,$nic);
  $res = array();
  $dups = array();

  foreach ($addrtab as $addr) {
    if (isset($addr[$type])) {
      if (!isset($dups[$addr[$type]])) {
	$res[] = $addr[$type];
	$dups[$addr[$type]] = 1;
      }
    }
  }
  return $res;
}
function res($type,$a=NULL,$b=NULL,$index=-1) {
  $res = lk_addr_item($type,$a,$b);
  if ($index == -1) return implode(',',$res);
  //if ($index == MSYS_LOOKUP_FIRST) {
  //$v = array_values($res);
  //return $res[0];
  //}
  return $res[$index];
}

//
// Some specialized functions
//
function lk_gw($type,$a=NULL,$b=NULL) {
  list($host,$nic) = _lk_defaults($a,$b);
  foreach (lk_addr($host,$nic) as $addr) {
    if (!isset($addr['netid'])) continue;
    if (!isset($addr['hostid'])) continue;
    if (!isset($addr[$type])) continue;
    if ($addr['hostid'] != 1) {
      global $cf;
      $netdat = &$cf['nets'][$addr['netid']];
      if ($type == 'ip4') {
	if (isset($netdat['gw'])) {
	  if (preg_match('/^\d+$/',$netdat['gw'])) {
	    return $netdat['ip4'].'.'.$netdat['gw'];
	  }
	  return $netdat['gw'];
	}
	return $netdat['ip4'].'.1';
      } elseif ($type == 'ip6') {
	if (isset($netdat['ip6gw'])) {
	  if (preg_match('/^\d+$/',$netdat['ip6gw'])) {
	    return $netdat['ip6'].'::'.dechex($netdat['ip6gw']);
	  }
	  return $netdat['ip6gw'];
	}
	if (isset($netdat['gw']) && preg_match('/^\d+$/',$netdat['gw'])) {
	  return $netdat['ip6'].'::'.dechex($netdat['gw']);
	}
	return $netdat['ip6'].'::1';
      } else {
	trigger_error('Invalid address type '.$type,E_USER_WARNING);
      }
    }
  }
  return NULL;
}
