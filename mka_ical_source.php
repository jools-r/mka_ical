<?php

function mka_ical($atts, $thing = '') {
	$time_start = microtime(true);
	global $txpcfg;
	extract(lAtts(array(
		'url' => '',
		'cachingtime' => 30,
		'googlemail' => '',
		'limit' => 0,
		'pastevents' => false,
		'yearview' => false,
		'fmttime' => '%H:%M',
		'fmtdate' => '%d.%m.%Y',
		'imgfolder' => '',
		'imgcat' => '',	
		'defaultimage' => 'none.gif',
		'break'  => 'li',
		'class' => __FUNCTION__,
		'cssid' => 'icalevents',
		'wraptag'  => 'ul',
		'form'  => ''
	), $atts));
	
	
	// yearview
	if($yearview !== false) {
		$pastevents = true;
	}

	if (strlen($thing) > 0) {
		$body = $thing;
	} else if (!empty($form)) {
		$body = fetch_form($form);
	} else {
		// Default body
		$body = '<span class="date">{date}</span>'
				.'<span class="title">{title}</span>'
				.'<span class="description">{description}</span>';
	}

	// get url from googlemail-address
	if (strlen($googlemail) > 0) {
		$tmp = explode("@", $googlemail);
		$gmail = $tmp[0];
		$urls[] = "http://www.google.com/calendar/ical/".$googlemail."%40googlemail.com/public/basic.ics";
	}
	
	// add given urls from attribute url
	if (strlen($url) > 0) {
		foreach (explode(",", $url) as $u)
			$urls[] = trim($u);
	}
	
	// no url --> Nothing to show.
	if (sizeof($urls) === 0) {
		return "Error: No url given.";
	}
	
	// get images from folder and/or category
	$show_img = false;
	if ((strlen($imgcat) > 0) || (strlen($imgfolder) > 0 )) {
		$images = array();
		
		// add images from given categories
		if (strlen($imgcat) > 0) {
			$images = mka_imgarrayfromcat($imgcat);	
		}
		
		// Add images from given folder
		if (file_exists($imgfolder)) {
			$images = array_merge(mka_imgarrayfromfolder($imgfolder));
		}

		if (sizeof($images) > 0) {
			$show_img = true;

			// get default image
			$defimg = ical_defimg($defaultimage);
		}
	}

	// get events from cache
	$events = checkCache($urls, $cachingtime);

	// loop trough events
	$i = 1;
	foreach ($events as $evt) {

		// TODO :: recurrence auswerten
		
		// is event in future or running?
		if (($pastevents || $evt->end > mktime()) && ($limit == 0 || $i <= $limit )) {

			// TODO :: TextileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
			$evt->sum = ical_formatText($evt->sum);
			$evt->des = ical_formatText($evt->des);

			$dateView = ical_getDateView($evt, $fmtdate, $fmttime);



			$keys = array(); $vals = array();
			if ($show_img) {
				$image = ical_img($evt->sum, $images, $defimg);
				$keys[] = '{imagesrc}';		$vals[] = $image['src'];
				$keys[] = '{imageid}';		$vals[] = $image['id'];
			}
			$keys[] = '{date}';		$vals[] = $dateView;
			$keys[] = '{title}';		$vals[] = $evt->sum;
			$keys[] = '{description}';	$vals[] = $evt->des;

			
			$dates[] = $evt->start;
			$out[] = parse(str_replace($keys, $vals, $body));
			

			$i++; // count for attr limit
		} // if ($evt->getEnd() > mktime() && ($limit == 0  || $i <= $limit ))

	}
	
	$gen =  "<!-- mka_ical - generated in ".((microtime(true)-$time_start)*1000)."ms -->";
	// definition of doWrap
	// http://www.consking.com/txp406/nav.html?textpattern/publish/taghandlers.php.html#dowrap

	// TODO
	if ($yearview) {
		for ($i=0;$i< count($dates);$i++) {
			$date = $dates[$i];
		}
		$ret = doWrap($out, $wraptag, $break, $class, '', '', '', $cssid).$gen;
	}
	else {
		$ret = doWrap($out, $wraptag, $break, $class, '', '', '', $cssid).$gen;
	}
	
	return $ret;
	
}


function ical_img($title, $images, $defimg) {
	$search = array(" ", ".", "-");
	$rep = array("", "", "");
	$t = str_replace($search, $rep, strtolower($title));
	foreach ($images as $im) {
		if (strpos($t, $im['name']) !== false) {
			return $im;
		}
	}
	// return default image src
	return $defimg;
}


function ical_getDateView($evt, $fmt_date, $fmt_time) {
	$fmt_dati = $fmt_date." ".$fmt_time;
	// event on one day
	if (date("dmY",$evt->start) == date("dmY",$evt->end-1)) {
		// no exact time definition
		if (date("H:i",$evt->start) == "00:00" && date("H:i",$evt->end) == "00:00") {
			$dateView = strftime($fmt_date, $evt->start);
		}
		else {
			$dateView = strftime($fmt_dati, $evt->start)."-".strftime($fmt_time, $evt->start + $evt->duration);
		}
	
	}	
	else {
		$dateView  = strftime($fmt_date, $evt->start)."-".strftime($fmt_date, $evt->end-1);
	}
	return $dateView;
}

function mka_makearr_sql($str, $type='string', $delimeter =',') {
	$ret = array();
	$arr = explode($delimeter, $str);
	foreach ($arr as $el) {
		if ($type == 'string')
			$ret[] = "'".trim($el)."'";
		else 
			$ret[] = $el;
	}
	return $ret;
}

function mka_imgarrayfromcat($cats) {
	$imgs = array();
	
	$cats = mka_makearr_sql($cats);
	$clause = 'category IN ('.implode(",", $cats).')';
	$attrs = 'id, name, ext';
	$rs = safe_rows($attrs,'txp_image', $clause);
	
	$prefix = "http://".$GLOBALS['prefs']["siteurl"]."/".$GLOBALS['prefs']['img_dir']."/";
	
	if (count($rs) != 0) {
		foreach ($rs as $col) {
			$col['src'] = $prefix.$col['id'].$col['ext'];
			$imgs[] = $col;	
		}
	}
	return $imgs;
}

function mka_imgarrayfromfolder($imgfolder) {
	$imgs = array();
	if ($handle = @opendir($imgfolder)) {
		while (($file = @readdir($handle)) !== false) {
			$img = array();
			$parts = explode(".", $file);
			$img['ext']  = (count($parts) > 1) ? ".".array_pop($parts) : '';
			$img['name'] = implode(".", $parts);
			$img['src'] = "http://".$GLOBALS['prefs']["siteurl"]."/".$imgfolder."/".$file;
			if (strlen($img['name']) > 0 && in_array(strtolower($img['ext']), array(".png", ".jpg", ".jpeg", ".gif"))) {
				$imgs[] = $img;
			}
		}
		closedir($handle);
	}
	return $imgs;
}


function ical_defimg($defimg) {
	$url = "http://".$GLOBALS['prefs']["siteurl"]."/";
	$img = array();
	// is defimg id?
	if (intval($defimg) !== 0) {
		$id = intval($defimg);
		$img = safe_row('id, name, ext', 'txp_image', 'id = '.$id);
	}
	
	// image with id not found?
	if (sizeof($img) === 0) {
		$parts = explode(".", basename($defimg));
		$img['ext']  = (count($parts) > 1) ? ".".array_pop($parts) : '';
		$img['name'] = implode(".", $parts);
		$img['src'] = $url.$defimg;
	}
	else {
		$img["src"] = $url.$GLOBALS['prefs']['img_dir']."/".$img['id'].$img['ext'];
	}
	return $img;
}

function ical_shorten_link($string) {
	$text_word_maxlength = 55;
	if(count($string) == 2) { $pre = ""; $url = $string[1]; }
	else { $pre = $string[1]; $url = $string[2]; }
	$shortened_url = $url;
	if (strlen($url) > $text_word_maxlength) $shortened_url = substr($url, 0, ($text_word_maxlength/2)) . "..." . substr($url, - ($text_word_maxlength-3-$text_word_maxlength/2));
	return $pre."<a href=\"".$url."\">".$shortened_url."</a>";
}

function ical_formatText($string) {
	$string = ' ' . $string;
	$string = preg_replace_callback("#(^|[\n ])([\w]+?://.*?[^ \"\n\r\t<]*)#is", "ical_shorten_link", $string);
	$string = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "$1<a href=\"http://$2\">$2</a>", $string);
	$string = str_replace("\n","<br/>",$string);
	return $string;
}
