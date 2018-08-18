<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'mka_ical';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.6.5';
$plugin['author'] = 'Martin Kozianka';
$plugin['author_uri'] = 'http://kozianka.de/';
$plugin['description'] = 'Display events from an ical-File (Google Calendar, ...)';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
// TXP 4.6 tag registration
if (class_exists('\Textpattern\Tag\Registry')) {
	Txp::get('\Textpattern\Tag\Registry')
		->register('mka_ical')
	;
}

// load SG_Ical
// https://github.com/fangel/SG-iCalendar

require_once(txpath.'/lib/sgical/SG_iCal.php');

function mka_ical($atts, $thing = '') {
	$time_start = microtime(true);
	global $txpcfg;
	extract(lAtts(array(
		'url' => '',
		'cachingtime' => 30,
		'googlemail' => '',
		'limit' => 0,
		'pastevents' => false,
		'fmttime' => '%H:%M',
		'fmtdate' => '%d.%m.%Y',
		'imgfolder' => '',
		'imgcat' => '',
		'defaultimage' => 'none.gif',
		'break' => 'li',
		'class' => __FUNCTION__,
		'cssid' => 'icalevents',
		'wraptag' => 'ul',
		'form' => ''
	), $atts));

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

	// Get url from googlemail-address
	if (strlen($googlemail) > 0) {
		$tmp = explode("@", $googlemail);
		$gmail = $tmp[0];
		$urls[] = "http://www.google.com/calendar/ical/".$googlemail."%40googlemail.com/public/basic.ics";
	}

	// Add given urls from attribute url
	if (strlen($url) > 0) {
		foreach (explode(",", $url) as $u)
			$urls[] = trim($u);
	}

	// No url --> Nothing to show.
	if (sizeof($urls) === 0) {
		return "Error: No url given.";
	}

	// Get images from folder and/or category
	$show_img = false;
	if ((strlen($imgcat) > 0) || (strlen($imgfolder) > 0 )) {
		$images = array();

		// Add images from given categories
		if (strlen($imgcat) > 0) {
			$images = mka_imgarrayfromcat($imgcat);
		}

		// Add images from given folder
		if (file_exists($imgfolder)) {
			$images = array_merge(mka_imgarrayfromfolder($imgfolder));
		}

		if (sizeof($images) > 0) {
			$show_img = true;

			// Get default image
			$defimg = ical_defimg($defaultimage);
		}
	}

	// Get events from cache
	$events = checkCache($urls, $cachingtime);

	// Loop through events
	$i = 1;
	foreach ($events as $evt) {

		// TODO :: recurrence auswerten

		// Is event in future or ongoing?
		if (($pastevents || $evt->end > time()) && ($limit == 0 || $i <= $limit )) {

			// TODO :: TextileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
			$evt->sum = ical_formatText($evt->sum);
			$evt->des = ical_formatText($evt->des);
			$evt->loc = ical_formatText($evt->loc);

			$dateView  = ical_getDateView($evt, $fmtdate, $fmttime);
			$startDate = ical_getDateTime($evt, $fmtdate, 0);
			$startTime = ical_getDateTime($evt, $fmttime, 0);
			$endDate   = ical_getDateTime($evt, $fmtdate, 1);
			$endTime   = ical_getDateTime($evt, $fmttime, 1);

			$keys = array(); $vals = array();
			if ($show_img) {
				$image = ical_img($evt->sum, $images, $defimg);
				$keys[] = '{imagesrc}';		$vals[] = $image['src'];
				$keys[] = '{imageid}';		$vals[] = $image['id'];
			}
			$keys[] = '{date}';		    $vals[] = $dateView;
			$keys[] = '{start_date}';	$vals[] = $startDate;
			$keys[] = '{start_time}';	$vals[] = $startTime;
			$keys[] = '{end_date}';		$vals[] = $endDate;
			$keys[] = '{end_time}';		$vals[] = $endTime;
			$keys[] = '{title}';		$vals[] = $evt->sum;
			$keys[] = '{description}';	$vals[] = $evt->des;
			$keys[] = '{location}';		$vals[] = $evt->loc;
			$keys[] = '{event_url}';	$vals[] = $evt->url;

			$out[] = parse(str_replace($keys, $vals, $body));

			$i++; // Count for attr limit
		} // if ($evt->getEnd() > time() && ($limit == 0  || $i <= $limit ))

	}

	return doWrap($out, $wraptag, $break, $class, '', '', '', $cssid);

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

function ical_getDateTime($evt, $formatter, $end) {
	$dateView = ($end == 1) ? strftime($formatter, $evt->end) : strftime($formatter, $evt->start);
	return $dateView;
}

function mka_makearr_sql($str, $type='string', $delimeter =',') {
	$ret = array();
	$arr = explode($delimeter, $str);
	foreach ($arr as $el) {
		if ($type == 'string') {
			$ret[] = "'".trim($el)."'";
		} else {
			$ret[] = $el;
        }
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
//	$string = utf8_decode(trim($string));
	return $string;
}

//
// mka_ical_eventcache.php
//

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
			$events[] = getPlainEvent($event);
		}

	}
	// sort events by startdate
	usort($events, "cmp_event");
	return $events;
}

function getPlainEvent($evt) {
	$plainEvent = new stdClass;
//	$plainEvent->start = $evt->getStart()." -- ".date("d.m.Y H:i", $evt->getStart());
	$plainEvent->start     = $evt->getStart();
	$plainEvent->end       = $evt->getEnd();
	$plainEvent->duration  = $evt->getDuration();
	$plainEvent->sum       = $evt->getSummary();
	$plainEvent->des       = $evt->getDescription();
	$plainEvent->loc       = $evt->getLocation();
	$plainEvent->url       = $evt->getEventUrl();
	$rec = $evt->getProperty('recurrence');
	if ($rec === null) {
		$plainEvent->rec = false;
	} else {
		$plainEvent->rec = getPlainRec($rec);
	}

	return $plainEvent;
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
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
	#mka_ical code {
		font-weight:bold;
		font: 105%/130% "Courier New", courier, monospace;
		background-color: #FFFFCC;
	}
	#mka_ical code.block {
		font-weight:normal;
		border:1px dotted #999;
		background-color: #FFFFCC;
		display:block;
		margin:10px 10px 20px;
		padding:10px;
	}
</style>
# --- END PLUGIN CSS ---
-->
<!--
# --- BEGIN PLUGIN HELP ---
<div id="mka_ical">

<h1>mka_ical - Help</h1>

<p style="color:red;"><strong>
The help section is not complete so if you have any questions please write a comment.<br/>
<a href="http://kozianka-online.de/mka_ical">http://kozianka-online.de/mka_ical</a>
</strong></p>


<p>With the mka_ical plugin it is possible to display Events from iCal URL or File (e.g. Google Calendar).<br/>
The plugin makes use of SG-iCalendar by Morten Fangel (http://sevengoslings.net/icalendar)</p>
<h2>How to use</h2>

<h3>Example 1 (Using a public Google Calendar)</h3>
<pre class="block"><code class="block">&lt;txp:mka_ical googlemail=&quot;YOURADDRESSNAME@googlemail.com&quot; /&gt;</code></pre>
<ul>
	<li><strong>googlemail</strong>: Your GoogleMail address.</li>
</ul>
<br/>


<h3>Example 2 (Using some options)</h3>
<pre class="block"><code class="block">&lt;txp:mka_ical url=&quot;http://url.of.ics.file/calendar.ics&quot;
	pastevents=&quot;1&quot;  limit=&quot;0&quot;
	img=&quot;1&quot; imgfolder=&quot;images/termine/&quot; defaultimg=&quot;none.gif&quot; /&gt;</code></pre>
<ul>
	<li><strong>url</strong>: URL of a Calendar File</li>
	<li><strong>pastevents</strong>: [0,1] Show past events?</li>
	<li><strong>limit</strong>: [0,1,2,3,4,..] Show only X events</li>
	<li><strong>img</strong>: [0,1] Show images?</li>
	<li><strong>imgfolder</strong>: Where are the images?</li>
	<li><strong>defaultimg</strong>: Name of the default image</li>
</ul>
<br/>

<h2>Output</h2>
<pre><code class="block">
&lt;ul id=&quot;icalevents&quot;&gt;
	&lt;li&gt;
		[&lt;img src=&quot;images/image.png&quot; border=&quot;0&quot; /&gt;]
		&lt;span class=&quot;date&quot;&gt;01.01.1111 11:11&lt;/span&gt;
		&lt;span class=&quot;title&quot;&gt;TITLE&lt;/span&gt;
		[&lt;p class=&quot;description&quot;&gt;DESCRIPTION&lt;/p&gt;]
	&lt;/li&gt;
	[...]
&lt;/ul&gt;
</code></pre>
<br/>

<h2>Defining own template</h2>

<h4>Embed template</h4>
<pre><code class="block">&lt;txp:mka_ical wraptag="" break="" googlemail=&quot;YOURADDRESSNAME@googlemail.com&quot;&gt;
&lt;p&gt;
  &lt;h4&gt;{date} - {title}&lt;/h4&gt;
  &lt;img src="{imagesrc}" border="0" align="left"/&gt;
  &lt;p&gt;{description}&lt;/p&gt;
&lt;/p&gt;
&lt;/txp:mka_ical&gt;</code></pre>

<h4>Using form attribute</h4>
<pre><code class="block">&lt;txp:mka_ical form="mka_ical_template" googlemail=&quot;YOURADDRESSNAME@googlemail.com&quot;/&gt;</code></pre>
<br/>

<h2>Available variables</h2>
<pre><code class="block"><code>{date} - Date
{title} - Title
{description} - Description
{imagesrc} - image link
{imageid} - image id (only for images from txp categories)</code></pre>
<br/>


<h2>CSS Template</h2>
<pre><code class="block">
ul#icalevents {
}

ul#icalevents li {
}

ul#icalevents li img {
}

ul#icalevents li span.date {
}

ul#icalevents li span.title {
}

ul#icalevents li p.description {
}
</code></pre>

<h2>Attributes</h2>

<pre><code class="block">
url (string, default: "", url or googlemail required)
Link to the ICS-File

googlemail
GoogleMail address (calendar must be public, url or googlemail required))

limit (int, default:0, optional)
Defines how many events are shown. Defaults to 0 (means all)

pastevents (boolean, default: FALSE, optional)
Display past events

cssid (string, default: "icalevents", optional)
Sets the id for the event list

details (boolean, default: TRUE, optional)
Display the details

cachingtime (int, default: 5 (minutes), optional)
local caching

fmtTime (string, default: "%H:%M", optional)
Format String for the Time (http://php.net/manual/function.strftime.php)

fmtDate (string, default: "%d.%m.%Y", optional)
Format String for the date (http://php.net/manual/function.strftime.php)

img (boolean, default: FALSE)
Display an image for each date (requires following attributes)

imgfolder (string, default: "images", optional)
image folder

imgcat (string, default: "", optional)
image category

defaultimage (string, default: "images/none.gif", optional)
path to or id of the default image

wraptag (string, default: "ul", optional)
Wraptag-Tag

break (string, default: "li", optional)
Break-Tag

class (string, default: "mka_ical", optional)
CSS-Class for wraptag-Tag

cssid (string, default: "icalevents", optional)
CSS-Id for wraptag-Tag
</code></pre>

</div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>
