<?php // BUILD: Remove line

namespace TKr\ICal;

/**
 * The wrapper for vevents. Will reveal a unified and simple api for
 * the events, which include always finding a start and end (except
 * when no end or duration is given) and checking if the event is
 * blocking or similar.
 *
 * Will apply the specified timezone to timestamps if a tzid is
 * specified
 *
 * @author Morten Fangel (C) 2008
 * @author Michael Kahn (C) 2013
 * @license http://creativecommons.org/licenses/by-sa/2.5/dk/deed.en_GB CC-BY-SA-DK
 */
class VEvent
{
    const DEFAULT_CONFIRMED = true;

    protected $uid;

    protected $start;
    protected $end;

    protected $summary;
    protected $description;
    protected $location;

    protected $laststart;
    protected $lastend;

    public $recurrence; //RRULE
    public $recurex;    //EXRULE
    public $excluded;   //EXDATE(s)
    public $added;      //RDATE(s)

    public $freq; //getFrequency() intouch\ical\Freq

    public $data;

    /**
     * Constructs a new intouch\ical\VEvent. Needs the ICal
     * supplied so it can query for timezones.
     * @param Line[] $data
     * @param ICal   $ical
     */
    public function __construct($data, ICal $ical, $yearsLimit = 3)
    {
        if ( isset($data['uid']) ) {
            $this->uid = $data['uid']->getData();
            unset($data['uid']);
        }

        if ( isset($data['rrule']) ) {
            $this->recurrence = new Recurrence($data['rrule']);
            unset($data['rrule']);
        }

        if ( isset($data['exrule']) ) {
            $this->recurex = new Recurrence($data['exrule']);
            unset($data['exrule']);
        }

        if ( isset($data['dtstart']) ) {
            $this->start = $this->getTimestamp($data['dtstart'], $ical);
            unset($data['dtstart']);
        }

        if ( isset($data['dtend']) ) {
            $this->end = $this->getTimestamp($data['dtend'], $ical);
            unset($data['dtend']);
        } elseif ( isset($data['duration']) ) {
            $dur = new Duration( $data['duration']->getData() );
            $this->end = $this->start + $dur->getDuration();
            unset($data['duration']);
        }

        //google cal set dtend as end of initial event (duration)
        if ( isset($this->recurrence) ) {
            //if there is a recurrence rule

            //exclusions
            if ( isset($data['exdate']) ) {
                foreach ($data['exdate'] as $exdate) {
                    foreach ($exdate->getDataAsArray() as $ts) {
                        $this->excluded[] = strtotime($ts);
                    }
                }
                unset($data['exdate']);
            }
            //additions
            if ( isset($data['rdate']) ) {
                foreach ($data['rdate'] as $rdate) {
                    foreach ($rdate->getDataAsArray() as $ts) {
                        $this->added[] = strtotime($ts);
                    }
                }
                unset($data['rdate']);
            }

            $until = $this->recurrence->getUntil();
            $count = $this->recurrence->getCount();
            //check if there is either 'until' or 'count' set
            if ($until) {
                //ok..
            } elseif ($count) {
                //if count is set, then figure out the last occurrence and set that as the end date
                $this->getFrequency();
                $until = $this->freq->lastOccurrence($this->start);
            } else {
                //forever... limit to 3 years
                $this->recurrence->setUntil('+' . $yearsLimit .' years');
                $until = $this->recurrence->getUntil();
            }
            //date_default_timezone_set( xx ) needed ?;
            $this->laststart = strtotime($until);
            $this->lastend = $this->laststart + $this->getDuration();
        }

        $imports = array('summary','description','location');
        foreach ($imports AS $import) {
            if ( isset($data[$import]) ) {
                $this->$import = $data[$import]->getData();
                unset($data[$import]);
            }
        }

        if ( isset($this->previous_tz) ) {
            date_default_timezone_set($this->previous_tz);
        }
        $this->data = Line::Remove_Line($data);
    }

    /**
     * Returns the Event Occurrences Iterator (if recurrence set)
     * @return intouch\ical\Freq
     */
    public function getFrequency($until = null)
    {
        if (! isset($this->freq)) {
            if ( isset($this->recurrence) ) {
                $this->freq = new Freq($this->recurrence, $this->start, $this->excluded, $this->added, $until);
            }
        }

        return $this->freq;
    }

    /**
     * Returns the UID of the event
     * @return string
     */
    public function getUID()
    {
        return $this->uid;
    }

    /**
     * Returns the summary (or null if none is given) of the event
     * @return string
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * Returns the description (or null if none is given) of the event
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the location (or null if none is given) of the event
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Returns true if the event is blocking (ie not transparent)
     * @return bool
     */
    public function isBlocking()
    {
        return !(isset($this->data['transp']) && $this->data['transp'] == 'TRANSPARENT');
    }

    /**
     * Returns true if the event is confirmed
     * @return bool
     */
    public function isConfirmed()
    {
        if ( !isset($this->data['status']) ) {
            return self::DEFAULT_CONFIRMED;
        } else {
            return $this->data['status'] == 'CONFIRMED';
        }
    }

    /**
     * Returns true if duration is multiple of 86400
     * @return bool
     */
    public function isWholeDay()
    {
        if (isset($this->data['x-microsoft-cdo-alldayevent']) &&
            strtolower($this->data['x-microsoft-cdo-alldayevent']) === 'true') {
            $result = true;
        } else {
            $dur = $this->getDuration();
            if ($dur > 0 && ($dur % 86400) == 0) {
                $result = true;
            } else {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Returns the timestamp for the beginning of the event
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Returns the timestamp for the end of the event
     * @return int
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Returns the timestamp for the end of the last event
     * @return int
     */
    public function getRangeEnd()
    {
        return max($this->end,$this->lastend);
    }

    /**
     * Returns the duration of this event in seconds
     * @return int
     */
    public function getDuration()
    {
        return $this->end - $this->start;
    }

    /**
     * Returns the given property of the event.
     * @param  string $prop
     * @return string
     */
    public function getProperty( $prop )
    {
        if ( isset($this->$prop) ) {
            return $this->$prop;
        } elseif ( isset($this->data[$prop]) ) {
            return $this->data[$prop];
        } else {
            return null;
        }
    }

    /**
     * Set default timezone (temporary) to get timestamps
     * @return string
     */
    protected function setLineTimeZone( Line $line )
    {
        if ( isset($line['tzid']) ) {
            if (!isset($this->previous_tz)) {
                $this->previous_tz = @ date_default_timezone_get();
            }
            $this->tzid = $line['tzid'];
            date_default_timezone_set($this->tzid);

            return true;
        }

        return false;
    }

    /**
     * Calculates the timestamp from a DT line.
     * @param $line Line
     * @return int
     */
    protected function getTimestamp( Line $line, ICal $ical )
    {
        if ( isset($line['tzid']) ) {
            $this->setLineTimeZone($line);
            //$tz = $ical->getTimeZoneInfo($line['tzid']);
            //$offset = $tz->getOffset($ts);
            //$ts = strtotime(date('D, d M Y H:i:s', $ts) . ' ' . $offset);
        }
        $ts = strtotime($line->getData());

        return $ts;
    }
}
