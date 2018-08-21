<?php

function checkCache($urls, $cachingtime)
{
    $hash = md5(implode(",", $urls));
	// Use cached json file in temp directory
    $fn = $GLOBALS['prefs']["tempdir"]. "/mka_ical_".$hash.".json";

    if (file_exists($fn) && (time()-filemtime($fn) < ($cachingtime*60))) {
        // Eventstring from cache file
        $cache_file = fopen($fn, "r");
        $data = fread($cache_file, filesize($fn));
        fclose($cache_file);
        return json_decode($data);
    } else {
        // Generate new eventstring
        $events = getEvents($urls);

        $cf = fopen($fn, "w+");
        fwrite($cf, json_encode($events));
        fclose($cf);

        return $events;
    }
}

function getEvents($urls)
{
    $events = array();

    // Instantiate ICal() library
    $reader = new TKr\ICal\ICal();

    foreach ($urls as $u) {
        $reader->setUrl($u, true);
        $e = $reader->getEvents();
        foreach ($e as $event) {
            // $plainEvents = getPlainEvents($event);
            // $events = array_merge($events, $plainEvents);
            $events[] = getPlainEvent($event);
        }
    }
    // Sort events by startdate
    usort($events, "cmp_event");
    return $events;
}

function getPlainEvent($evt)
{
    $plainEvent = array();

    $plainEvent = new stdClass;
    $plainEvent->start     = $evt->getStart();
    $plainEvent->end       = $evt->getEnd();
    $plainEvent->duration  = $evt->getDuration();
    $plainEvent->sum       = $evt->getSummary();
    $plainEvent->des       = $evt->getDescription();
    $plainEvent->loc       = $evt->getLocation();
    $plainEvent->uid       = $evt->getUID();

    $rec = $evt->getProperty('recurrence');
    if ($rec === null) {
        $plainEvent->rec = false;
    } else {
        // $plainEvent->rec = json_encode(getPlainRec($rec));
        $plainEvent->rec = getPlainRec($rec);
    }
    return $plainEvent;
}

function getPlainRec($rec)
{
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

function cmp_event($a, $b)
{
    $da = $a->start;
    $db = $b->start;
    if ($da == $db) {
        return 1;
    }
    return ($da < $db) ? -1 : 1;
}
