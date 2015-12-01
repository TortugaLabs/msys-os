<?php

//
// Install a tree
//
function instree($src,$dst) {
  // Make an inventory of files
  $dirs = array('');
  $flst = array();
  $dlst = array();
  $perms = array();
  while (count($dirs)) {
    $d = array_shift($dirs);
    $spath = $d == '' ? $src : $src.'/'.$d;
    if ($d == '') {
      $spath = $src;
      $dpath = '';
    } else {
      $spath = $src.'/'.$d;
      $dpath = $d.'/';
    }
    if (false !== ($dh = opendir($spath))) {
      while (false !== ($f = readdir($dh))) {
	if ($f == '.' || $f == '..' ) continue;
	//
	// Pattern filters...
	//
	if (preg_match('/~$/',$f)) continue;
	if (preg_match('/^#/',$f)) continue;
	$p = $dpath.$f;
	$fpath = $src.'/'.$p;
	if ($f == 'perms.txt') {
	  if (false !== ($fh = fopen($fpath,'r'))) {
	    while (false !== ($ln = fgets($fh))) {
	      $ln = preg_replace('/#.*$/','',$ln);
	      $ln = preg_replace('/^\s+/','',$ln);
	      $ln = preg_replace('/\s+$/','',$ln);
	      if ($ln == '') continue;
	      list($m,$pm) = preg_split('/\s+/',$ln,2);
	      $perms[$dpath.$m] = $pm;
	    }
	    fclose($fh);
	  } else {
	    trigger_error('unable to open '.$fpath,E_USER_WARNING);
	  }
	  continue;
	}
	// We ignore symbolic and any other special files links...
	if (is_link($fpath)) continue;
	if (!is_dir($fpath) && !is_file($fpath)) continue;
	if (is_dir($fpath)) {
	  array_push($dirs,$p);
	  array_push($dlst,$p);
	} else {
	  array_push($flst,$p);
	}
      }
      closedir($dh);
    } else {
      trigger_error('Invalid source path '.$spath,E_USER_ERROR);
      return '# <INVALID SOURCE PATH>'.NL;
    }
  }
  $txt = '# INSTREE '.$src.' => '.$dst.NL;
  // Make sure the directory tree exists...
  $txt .= '[ ! -d "'.$dst.'" ] && mkdir -p "'.$dst.'"'.NL;
  sort($dlst);
  foreach ($dlst as $d) {
    $p = $dst.'/'.$d;
    $txt .= '[ ! -d "'.$p.'" ] && mkdir -p "'.$p.'"'.NL;
    if (isset($perms[$d])) $txt .= 'fixattr --mode='.$perms[$d].' "'.$p.'"'.NL;
  }
  // Update files...
  foreach ($flst as $f) {
    $p = $dst.'/'.$f;
    $txt .= 'fixfile --nobackup ';
    if (isset($perms[$f])) $txt .= '--mode='.$perms[$f].' ';
    $txt.= '"'.$p.'" <<'.QEOFMARK.NL;
    if (defined('BRIEF_OUTPUT')) {
      $txt .= '# SKIPPED CONTENTS FOR '.$f.NL;
    } else {
      $txt .= file_get_contents($src.'/'.$f);
    }
    $txt.= NL.EOFMARK.NL;
  }

  // Prune directories
  $txt .= 'prune_instree "'.$dst.'"';
  $txt .= ' '.implode(' ',$dlst);
  $txt .= ' '.implode(' ',$flst).NL;

  return $txt;
}
