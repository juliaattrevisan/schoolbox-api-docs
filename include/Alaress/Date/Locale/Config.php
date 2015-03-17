<?php
/**
 * Alaress_Date_Locale_Config
 *
 * Localisation for Australian date formats. Define your own getters/setters or
 * other formats specific to this localisation if you want.
 *
 * Alternatively, subclass this instead e.g.
 *
 * Alaress_Date_Locale_Melbourne extends Alaress_Date_Locale_Australian
 * <define extra class vars, define extra methods, etc>
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Config\Loader;

class Alaress_Date_Locale_Config extends Alaress_Date_Locale_Abstract {

    /**
     * @var Loader
     */
    var $config;

    /**
     * @return Loader
     */
    public function getConfig(){
        if(!$this->config instanceof Loader) {
            $this->config = new Loader;
        }

        return $this->config;
    }

    /**
     * Get the preferred local format
     *
     * @return string
     */
    public function localFormat() {

        return $this->getConfig()->get('date_local').' '.$this->getConfig()->get('date_time');
    }

    /**
     * Get the preferred local short format
     *
     * @return string
     */
    public function localShortFormat() {
        return $this->getConfig()->get('date_local');
    }

    /**
     * Get a long format
     *
     * @return string
     */
    public function longFormat() {
        return  $this->getConfig()->get('date_long').' '.$this->getConfig()->get('date_time');
    }

    /**
     * Get a long date only format
     *
     * @return string
     */
    public function longDateOnlyFormat() {
        return $this->getConfig()->get('date_long');
    }

    /**
     * Get a shortened format
     *
     * @return string
     */
    public function shortFormat() {
        return $this->getConfig()->get('date_short').' '.$this->getConfig()->get('date_time');
    }

    /**
     * Get the date-only format
     *
     * @return string
     */
    public function dateOnlyFormat() {
        return $this->getConfig()->get('date_short');
    }

    /**
     * Get the time-only format
     *
     * @return string
     */
    public function timeOnlyFormat() {
        return $this->getConfig()->get('date_time');
    }

    /**
     * Get the calendar date format
     *
     * @return string
     */
    public function calendarDate() {
        return $this->getConfig()->get('date_only');
    }

    /**
     * Get the calendar datetime format
     *
     * @return string
     */
    public function calendarDateTime() {
        return $this->getConfig()->get('date_only').' '.$this->getConfig()->get('date_time');
    }
}
