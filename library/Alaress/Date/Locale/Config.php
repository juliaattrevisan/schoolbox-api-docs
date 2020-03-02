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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\Loader as ConfigLoader;
use \Schoolbox\IConfigLoader;

class Alaress_Date_Locale_Config extends Alaress_Date_Locale_Abstract
{

    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * @return IConfigLoader
     */
    public function getConfig(): IConfigLoader
    {
        if (!$this->config instanceof IConfigLoader) {
            $this->setConfig(ConfigLoader::getInstance());
        }

        return $this->config;
    }

    /**
     * @param \Schoolbox\IConfigLoader $loader
     *
     * @return $this
     */
    public function setConfig(IConfigLoader $loader): self
    {
        $this->config = $loader;
        return $this;
    }

    /**
     * Get the preferred local format
     *
     * @return string
     */
    public function localFormat(): string
    {

        return $this->getConfig()->get('date_local') . ' ' . $this->getConfig()->get('date_time');
    }

    /**
     * Get the preferred local short format
     *
     * @return string
     */
    public function localShortFormat(): string
    {
        return $this->getConfig()->get('date_local');
    }

    /**
     * Get a long format
     *
     * @return string
     */
    public function longFormat(): string
    {
        return $this->getConfig()->get('date_long') . ' ' . $this->getConfig()->get('date_time');
    }

    /**
     * Get a long date only format
     *
     * @return string
     */
    public function longDateOnlyFormat(): string
    {
        return $this->getConfig()->get('date_long');
    }

    /**
     * Get a shortened format
     *
     * @return string
     */
    public function shortFormat(): string
    {
        return $this->getConfig()->get('date_short') . ' ' . $this->getConfig()->get('date_time');
    }

    /**
     * Get a short date only format
     *
     * @return string
     */
    public function shortDateOnlyFormat(): string
    {
        return $this->getConfig()->get('date_short');
    }

    /**
     * Get the date-only format
     *
     * @return string
     */
    public function dateOnlyFormat(): string
    {
        return $this->getConfig()->get('date_only');
    }

    /**
     * Get the time-only format
     *
     * @return string
     */
    public function timeOnlyFormat(): string
    {
        return $this->getConfig()->get('date_time');
    }

    /**
     * Get the calendar date format
     *
     * @return string
     */
    public function calendarDate(): string
    {
        return $this->getConfig()->get('date_only');
    }

    /**
     * Get the calendar datetime format
     *
     * @return string
     */
    public function calendarDateTime(): string
    {
        return $this->getConfig()->get('date_only') . ' ' . $this->getConfig()->get('date_time');
    }

    /**
     * Get the days which are the weekends for this locale
     *
     * @return array
     */
    public function weekendDays(): array
    {
        return explode(',', $this->getConfig()->get('locale_weekend'));
    }
}
