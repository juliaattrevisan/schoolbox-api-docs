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

use \Schoolbox\Config\ConfigMapper;
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
            $this->setConfig(ConfigMapper::getInstance());
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
        $this->config = new class($loader) implements IConfigLoader
        {

            private const KEYS = [
                'date_local',
                'date_time',
                'date_long',
                'date_short',
                'date_time',
                'date_only',
                'locale_weekend',
            ];

            /** @var string[] */
            private $formats = [];


            /**
             * @param IConfigLoader $conf
             */
            public function __construct(IConfigLoader $conf)
            {
                // cache this
                foreach (self::KEYS as $key) {
                    $this->formats[$key] = $conf->get($key);
                }
            }


            /**
             * Return the value of the given config var
             *
             * @param string $var
             * @return mixed
             */
            public function get($var)
            {
                return $this->formats[$var];
            }


            /**
             * @return void
             */
            public function dropCache()
            {
                // noop
            }

        };
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
     * Get the calendar datetime format
     *
     * @return string
     */
    public function weekendDays(): array
    {
        return explode(',', $this->getConfig()->get('locale_weekend'));
    }
}
