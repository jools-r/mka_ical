<?php

// TXP 4.6+ tag registration
if (class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('mka_ical')
        ->register('mka_format_date')
        ->register('mka_if_first_event')
        ->register('mka_if_last_event')
        ->register('mka_if_different')
    ;
}

// -------------------------------------------------------------
function mka_ical($atts, $thing = '')
{
    global $mka_this_ical_event;

    extract(lAtts(array(
        'url' => '',
        'cachingtime' => 30,
        'googlemail' => '',
        'googlemaildomain' => 'googlemail',
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
        'html_id' => 'icalevents',
        'wraptag' => 'ul',
        'form' => ''
    ), $atts, false));

    // Cater for legacy cssid -> html_id
    // if cssid is specified and not default value, update html_id
    if ($cssid != $html_id) {
        $html_id = $cssid;
    }

    if (strlen($thing) > 0) {
        // Use contained code
        $body = $thing;
    } elseif (!empty($form)) {
        // OR: Get form if specified
        $body = fetch_form($form);
    } else {
        // ELSE: Default output
        $body =  '<span class="date">{date}</span>'
                .'<span class="title">{title}</span>'
                .'<span class="description">{description}</span>';
    }

    // Get url from googlemail-address
    if (strlen($googlemail) > 0) {
        $tmp = explode("@", $googlemail);
        if (count($tmp > 1)) {
            // Full email address: just encode '@' to '%40'
            $gmail = rawurlencode($googlemail);
        } else {
            // Just username: construct email using attribute "googlemaildomain" (e.g. googlemail / gmail)
            $gmail = $tmp[0]."%40".$googlemaildomain.".com";
        }
//      $urls[] = "https://www.google.com/calendar/ical/".$gmail."/public/basic.ics";
        $urls[] = "https://calendar.google.com/calendar/ical/".$gmail."/public/basic.ics";
    }

    // Add given urls from attribute url
    if (strlen($url) > 0) {
        foreach (explode(",", $url) as $u) {
            $urls[] = trim($u);
        }
    }

    // No url --> nothing to show --> error message.
    if (sizeof($urls) === 0) {
        return "mka_ical Error: No url specified.";
    }

    // Get images from folder and/or category
    $show_img = false;
    if ((strlen($imgcat) > 0) || (strlen($imgfolder) > 0)) {
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

    // Start counter
    $count = 0;
    // Get uid of last item
    $last_event = end($events)->uid;

    // Loop through events
    foreach ($events as $evt) {

        // TODO :: recurrence auswerten

        // Is event in future or ongoing?
        // if (($evt->getEnd() > time()) && ($limit == 0  || $count < $limit ))
        if (($pastevents || $evt->end > time()) && ($limit == 0 || $count < $limit)) {

            // Advance counter
            ++$count;

            // First event
            $mka_this_ical_event['is_first'] = ($count == '1');

            // Last event in array or last in limit
            if (($evt->uid == $last_event) || ($count == $limit)) {
                $mka_this_ical_event['is_last'] = true;
            }

            // TODO :: TextileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
            $evt->sum = ical_formatText($evt->sum);
            $evt->des = ical_formatText($evt->des);
            $evt->loc = ical_formatText($evt->loc);

            $dateView  = ical_getDateView($evt, $fmtdate, $fmttime);
            $startDatetime = ical_getDateTime($evt, "%Y-%m-%d %H:%M", 0); // datetime format
            $startDate = ical_getDateTime($evt, $fmtdate, 0);
            $startTime = ical_getDateTime($evt, $fmttime, 0);
            $endDatetime = ical_getDateTime($evt, "%Y-%m-%d %H:%M", 0); // datetime format
            $endDate   = ical_getDateTime($evt, $fmtdate, 1);
            $endTime   = ical_getDateTime($evt, $fmttime, 1);
            $duration  = gmdate("z:H:i:s", $evt->duration); // convert seconds to days:hours:minutes:seconds

            $keys = array();
            $vals = array();
            if ($show_img) {
                $image = ical_img($evt->sum, $images, $defimg);
                $keys[] = '{imagesrc}';
                $vals[] = $image['src'];
                $keys[] = '{imageid}';
                $vals[] = $image['id'];
            }
            $keys[] = '{date}';
            $vals[] = $dateView;
            $keys[] = '{start_datetime}';
            $vals[] = $startDatetime;
            $keys[] = '{start_date}';
            $vals[] = $startDate;
            $keys[] = '{start_time}';
            $vals[] = $startTime;
            $keys[] = '{end_datetime}';
            $vals[] = $endDatetime;
            $keys[] = '{end_date}';
            $vals[] = $endDate;
            $keys[] = '{end_time}';
            $vals[] = $endTime;
            $keys[] = '{duration}';
            $vals[] = $duration;
            $keys[] = '{title}';
            $vals[] = $evt->sum;
            $keys[] = '{description}';
            $vals[] = $evt->des;
            $keys[] = '{location}';
            $vals[] = $evt->loc;
            $keys[] = '{uid}';
            $vals[] = $evt->uid;

            $out[] = parse(str_replace($keys, $vals, $body));
        }

    }

    return doWrap($out, $wraptag, $break, $class, '', '', '', $html_id);
}

// -------------------------------------------------------------

/* Simple date reformatter */
/* Use with {start_datetime} and {end_datetime} */
/* date formats as per http://php.net/manual/en/function.strftime.php */

function mka_format_date($atts, $thing = '')
{
    extract(lAtts(array(
        'format' => ''
    ), $atts, false));

    if ($format === '') {
        return $thing;
    } else {
        return strftime($format, strtotime($thing));
    }
}

// -------------------------------------------------------------
function mka_if_first_event($atts, $thing = null)
{
    global $mka_this_ical_event;
    $x = !empty($mka_this_ical_event['is_first']);
    return isset($thing) ? parse($thing, $x) : $x;
}

// -------------------------------------------------------------
function mka_if_last_event($atts, $thing = null)
{
    global $mka_this_ical_event;
    $x = !empty($mka_this_ical_event['is_last']);
    return isset($thing) ? parse($thing, $x) : $x;
}

// -------------------------------------------------------------
function mka_if_different($atts, $thing)
{
    static $last = array();

    extract(lAtts(array('key' => md5($thing)), $atts));
    $out = parse($thing, 1);

    if (empty($last[$key]) || $out != $last[$key]) {
        return $last[$key] = $out;
    } else {
        return parse($thing, 0);
    }
}

// -------------------------------------------------------------
function ical_img($title, $images, $defimg)
{
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

// -------------------------------------------------------------
function ical_getDateView($evt, $fmt_date, $fmt_time)
{
    $date_separator = " – ";

    $fmt_dati = $fmt_date." ".$fmt_time;
    // event on one day
    if (date("dmY", $evt->start) == date("dmY", $evt->end-1)) {
        // no exact time definition
        if (date("H:i", $evt->start) == "00:00" && date("H:i", $evt->end) == "00:00") {
            $dateView = strftime($fmt_date, $evt->start);
        } else {
            $dateView = strftime($fmt_dati, $evt->start).$date_separator.strftime($fmt_time, $evt->start + $evt->duration);
        }
    } else {
        $dateView  = strftime($fmt_date, $evt->start).$date_separator.strftime($fmt_date, $evt->end-1);
    }
    return $dateView;
}

// -------------------------------------------------------------
function ical_getDateTime($evt, $formatter, $end)
{
    $dateView = ($end == 1) ? strftime($formatter, $evt->end) : strftime($formatter, $evt->start);
    return $dateView;
}

// -------------------------------------------------------------
function mka_makearr_sql($str, $type='string', $delimeter =',')
{
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

// -------------------------------------------------------------
function mka_imgarrayfromcat($cats)
{
    $imgs = array();

    $cats = mka_makearr_sql($cats);
    $clause = 'category IN ('.implode(",", $cats).')';
    $attrs = 'id, name, ext';
    $rs = safe_rows($attrs, 'txp_image', $clause);

    $prefix = "http://".$GLOBALS['prefs']["siteurl"]."/".$GLOBALS['prefs']['img_dir']."/";

    if (count($rs) != 0) {
        foreach ($rs as $col) {
            $col['src'] = $prefix.$col['id'].$col['ext'];
            $imgs[] = $col;
        }
    }
    return $imgs;
}

// -------------------------------------------------------------
function mka_imgarrayfromfolder($imgfolder)
{
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

// -------------------------------------------------------------
function ical_defimg($defimg)
{
    $url = "http://".$GLOBALS['prefs']["siteurl"]."/";
    $img = array();
    // Is defimg id?
    if (intval($defimg) !== 0) {
        $id = intval($defimg);
        $img = safe_row('id, name, ext', 'txp_image', 'id = '.$id);
    }

    // Image with id not found?
    if (sizeof($img) === 0) {
        $parts = explode(".", basename($defimg));
        $img['ext']  = (count($parts) > 1) ? ".".array_pop($parts) : '';
        $img['name'] = implode(".", $parts);
        $img['src'] = $url.$defimg;
    } else {
        $img["src"] = $url.$GLOBALS['prefs']['img_dir']."/".$img['id'].$img['ext'];
    }
    return $img;
}

// -------------------------------------------------------------
function ical_shorten_link($string)
{
    $text_word_maxlength = 55;
    if (count($string) == 2) {
        $pre = "";
        $url = $string[1];
    } else {
        $pre = $string[1];
        $url = $string[2];
    }
    $shortened_url = $url;
    if (strlen($url) > $text_word_maxlength) {
        $shortened_url = substr($url, 0, ($text_word_maxlength/2)) . "…" . substr($url, - ($text_word_maxlength-3-$text_word_maxlength/2));
    }
    return $pre."<a href=\"".$url."\">".$shortened_url."</a>";
}

// -------------------------------------------------------------
function ical_formatText($string)
{
    $string = ' ' . $string;
    $string = preg_replace_callback("#(^|[\n ])([\w]+?://.*?[^ \"\n\r\t<]*)#is", "ical_shorten_link", $string);
    $string = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "$1<a href=\"http://$2\">$2</a>", $string);
    $string = str_replace("\n", "<br/>", $string);
    return $string;
}
