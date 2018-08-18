<?php
/**
 * Compact PHP code.
 *
 * Strip comments, combine entire library into one file.
 *
 * Modified by Jurriaan Pruis - Better 'compression'
 *
 **/
 
require_once('lib/compactor.php');

if ($argc < 3) {
  print "Strip unnecessary data from PHP source files.\n\n\tUsage: php phpcompactor.php DESTINATION.php SOURCE.php\n";
  exit;
}
 
$source = $argv[2];
$target = $argv[1];
print "Compacting $source into $target.\n";
$compactor = new Compactor($target);

$compactor->compact('mka_ical_source.php');
$compactor->compact('mka_ical_eventcache.php');

$compactor->compact('sgical/sgical.php');

$compactor->report();
$compactor->close();
