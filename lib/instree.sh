#!/bin/sh
#
# Macros needed for INSTREE
#
lnpkg_bin() {
  local srcdir="$1" dstdir="$2"
  local i j k
  for i in $srcdir/*
  do
    [ -e "$i" ] || continue
    j="$dstdir/$(basename "$i")"
    if [ -L "$j" ] ; then
      k=$(readlink "$j")
      [ x"$k" = x"$i" ] && continue
    fi
    rm -f "$j"
    echo "linking $i $j"
    ln -s "$i" "$j"
  done
}

clean_links() {
  local bindir="$1" prefix="$2"
  local i j
  prefix=$(echo "$prefix" | sed 's!/*$!!')

  for i in $bindir/*
  do
    [ -L "$i" ] || continue
    j=$(readlink "$i")
    (echo "$j" | grep -q "^$prefix/") || continue
    [ -e "$j" ] && continue
    echo "x rm $i" 1>&2
    rm "$i"
  done
}

prune_instree() {
  local target="$1" ; shift
  local fsobjs=$(find $target)
  local i j

  for i in $fsobjs
  do
    [ -e $i ] || continue # Could have been blown by a rm -rf...
    [ "$i"  = "$target" ] && continue
    for j in $*
    do
      [ "$i" != "$target/$j" ] && continue
      i='' # Matched!
      break
    done
    [ -z "$i" ] && continue
    if [ -d "$i" ] ; then
      echo "Deleting directory $i"
      rm -rf "$i"
    else
      echo "Deleting file $i"
      rm -f $i
    fi
  done
}
