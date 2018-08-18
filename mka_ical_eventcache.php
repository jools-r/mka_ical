<?php

function checkCache($urls, $cachingtime) {
	
	$hash = md5(implode(",", $urls));
	$fn = "files/mka_ical_".$hash.".json";
	
	if (file_exists($fn) && (time()-filemtime($fn) < ($cachingtime*60))) {
		// eventstring from cache file
		$cache_file = fopen($fn,"r");
		$data = fread($cache_file, filesize($fn));
		fclose($cache_file);
		return json_decode($data);
	} else {
		// generate new eventstring
		$events = getEvents($urls);

		$cf = fopen($fn,"w+");
		fwrite($cf, json_encode($events));
		fclose($cf);
		
		return $events;
	}
}

function getEvents($urls) {
	$events = array();
	$reader = new SG_iCal();
	foreach($urls as $u) {
		$reader->setUrl($u, true);
		$e = $reader->getEvents();
		foreach($e as $event) {
			$plainEvents = getPlainEvents($event);
			$events = array_merge($events, $plainEvents); 
		}
	}
	// sort events by startdate	
	usort($events, "cmp_event");
	return $events;
}

function getPlainEvents($evt) {
	$plainEvents = array();
	
	$plainEvent = new stdClass;
	$plainEvent->start = $evt->getStart();
	$plainEvent->end = $evt->getEnd();
	$plainEvent->duration = $evt->getDuration(); 
	$plainEvent->sum = $evt->getSummary();
	$plainEvent->des = $evt->getDescription();
	$plainEvent->loc = $evt->getLocation();

	$rec = $evt->getProperty('recurrence');
	if ($rec === null) {
		$plainEvent->rec = false;
	} else {
		$plainEvent->rec = json_encode(getPlainRec($rec));
	}
	
	$timestamps = null;
	if ($evt->getFrequency()) {
		$freq = $evt->getFrequency();
		$timestamps = $freq->getAllOccurrences();
		$i = 1;
		foreach ($timestamps as $ts) {
			
			if ($ts !== $plainEvent->start) {
				$recEvent = clone $plainEvent;
				$recEvent->start = $ts;
				$recEvent->end = $ts + $plainEvent->duration;
				$recEvent->duration = $plainEvent->duration;
				$plainEvents[] = $recEvent;
			}
		}
	}
	$plainEvents[] = $plainEvent;

	return $plainEvents;
}


function getPlainRec($rec) {
	$r = new stdClass; 
	$class_methods = get_class_methods($rec);
	foreach ($class_methods as $method) {
		if (substr($method, 0, 3) === 'get') { 
			$prop = strtolower(substr($method, 3));
			$r->$prop = $rec->$method();
		}
	}
	return $r;
}

function cmp_event($a, $b) {
    $da = $a->start;
    $db = $b->start;
    if ($da == $db) {
        return 1;
    }
    return ($da < $db) ? -1 : 1;
}

?>
