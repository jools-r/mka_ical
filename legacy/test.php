<?php header("Content-Type: text/html; charset=utf-8"); ?>
<h1>Test</h1>
<pre>
<?php

date_default_timezone_set('Europe/Berlin');

require_once 'sgical/sgical.php';
require_once 'mka_ical_eventcache.php';
require_once 'mka_ical_source.php';

$time_start = microtime(true);


$urls[] = "ics/basic.ics";
$evs = checkCache($urls, 0);

/*
	$dir = "ics";
	// Get image names from folder
	$d = dir($dir);
	$imgNames = array();
	while (false !== ($entry = $d->read())) {
		if (in_array(substr($entry, strlen($entry)-3, 3), array("png", "jpg", "jpeg", "gif")))
	   		$imgNames[] = trim($entry);
	}
	$d->close();
	print_r($imgNames);
*/

echo "<p>".((microtime(true)-$time_start)*1000)."ms</p>";

?>
</pre>