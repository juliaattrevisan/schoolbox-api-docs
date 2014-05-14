<?php
/**
 * Date period iterator for events based on a timetable.
 *
 * Like DatePeriod, the iterator does NOT include the end date.
 *
 * @see       DatePeriod
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_TimetableDatePeriod implements Iterator {

    /**
     * @var DateTime
     */
    protected $start;

    /**
     * @var DateTime
     */
    protected $end;

    /**
     * @var Datetime
     */
    protected $current;

    /**
     * @var int
     */
    protected $index;

    /**
     * @var bool
     */
    protected $invert;

    /**
     * @var int
     */
    protected $campusId;

    /**
     * @var External_Timetable
     */
    protected $externalTimetable;

    /**
     * @var int
     */
    protected $dayNumber;

    /**
     * @param  DateTime           $start
     * @param  DateTime           $end
     * @param  External_Timetable $externalTimetable
     * @param  int                $campusId
     * @throws InvalidArgumentException if $campusId is not a valid campus
     * @throws InvalidArgumentException if $start does not have a timetable day
     */
    public function __construct(DateTime $start, DateTime $end, External_Timetable $externalTimetable, $campusId) {

        $this->start             = clone $start;
        $this->end               = clone $end;
        $this->externalTimetable = $externalTimetable;
        $this->campusId          = $campusId;

        // Deal exclusively in dates
        $this->start->setTime(0,0,0);
        $this->end->setTime(0,0,0);

        // Figure out the day that $start starts at
        $dayNumber = $externalTimetable->getDayNumber($campusId, $this->start->getTimestamp());
        if ($dayNumber === false) {
            throw new InvalidArgumentException("Campus \"{$campusId}\" does not exist");
        }
        if (!$dayNumber) {
            throw new InvalidArgumentException("Start date \"{$this->start->format('Y-m-d')}\" is not timetabled");
        }
        $this->dayNumber = $dayNumber;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     *
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current() {
        return $this->current;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     *
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next() {
        $this->index++;
        if ($this->invert) {
            $this->current = $this->externalTimetable->getPreviousDateForDayNumber(
                $this->campusId,
                $this->dayNumber,
                $this->current->getTimestamp()
            );
        }
        else {
            $this->current = $this->externalTimetable->getNextDateForDayNumber(
                $this->campusId,
                $this->dayNumber,
                $this->current->modify('+1 day')->getTimestamp()
            );
        }
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     *
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key() {
        return $this->index;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     *
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     *       Returns true on success or false on failure.
     */
    public function valid() {
//        return $this->invert ?
//            $this->current > $this->end:
//            $this->current < $this->end;
        // < 0 if $current < $end, > 0 if $current > $end
        $comparison = strcmp($this->current->format('Ymd'), $this->end->format('Ymd'));
        return $this->invert ?
            $comparison > 0:
            $comparison < 0;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind() {
        $this->index   = 0;
        $this->current = $this->start;
        $this->invert  = ($this->end < $this->start);
    }
}
