<?php
/**
 * Date column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

class Date extends Datetime implements IColumnType
{
    protected $input_format  = 'Y-m-d';
    protected $output_format = 'Y-m-d';

    /**
     * This value will hydrate to null.
     */
    protected const ZERO_VALUE = '0000-00-00';

    /**
     * Either null or a time format accepted by strtotime(), eg '00:00:00'
     *
     * @var string|null
     */
    private $time;

    /**
     * Enable the date to be hydrated with a specified time component.
     *
     * Eg, term start and end dates are date-only, but logically require the correct
     * time component in the code-base to simplify datetime comparisons.
     *
     * @param null|string $time in 'HH:MM:SS' format
     */
    public function __construct($time = null)
    {
        parent::__construct();
        $this->time = $time;
    }


    public function hydrate($value, $mapper = null)
    {
        $hydrated = parent::hydrate($value, $mapper);
        if (!empty($this->time) && !$hydrated->isNull()) {
            $hydrated->modify($this->time);
        }
        return $hydrated;
    }

    /**
     * Return a hydrator that will generate dates with time component at start of day
     *
     * @return static
     */
    public static function startOfDay()
    {
        return new static('00:00:00');
    }

    /**
     * Return a hydrator that will generate dates with time component at end of day
     *
     * @return static
     */
    public static function endOfDay()
    {
        return new static('23:59:59');
    }
}
