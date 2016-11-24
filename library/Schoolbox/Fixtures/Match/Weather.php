<?php
/**
 * Weather
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Match;

use \Schoolbox\Fixtures\Match\Model as Match;

class Weather
{

    /**
     * @var Match
     */
    private $match;

    /**
     * Forecast length. If the match occurs beyond this many days
     * into the future, don't bother trying to find weather data
     *
     * @var int
     */
    private $forecastLength = 10;

    /**
     * The icon to display
     *
     * @var string
     */
    private $icon = '';

    /**
     * The weather condition to display
     *
     * @var string
     */
    private $condition = '';

    /**
     * Temperature to display
     *
     * @var string
     */
    private $temp = '';

    /**
     * Did we find any data?
     * 
     * @var bool
     */
    private $hasResults = false;

    /**
     * Weather constructor.
     *
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setMatch($match);

        $this->findForecast();
    }

    /**
     * Find the forecast
     *
     */
    private function findForecast()
    {
        $forecast = $this->findWeather($this->getMatch());

        $this->setIcon($this->parseIcon($forecast));
        $this->setCondition($forecast['conditions']);
        $this->setTemp($forecast['temp']['high']);
    }

    /**
     * Spawn our own icon
     *
     * @param $forecast
     * @return string
     */
    private function parseIcon($forecast)
    {
        /* Map of conditions => icon
         */
        $map = [
            'chanceflurries' => 'icon-rain',
            'chancerain' => 'icon-rain',
            'chancesleet' => 'icon-rain',
            'chancesnow' => 'icon-rain',
            'chancetstorms' => 'icon-storm',
            'clear' => 'icon-clear',
            'cloudy' => 'icon-cloudy',
            'flurries' => 'icon-rain',
            'fog' => 'icon-cloudy',
            'hazy' => 'icon-cloudy',
            'mostlycloudy' => 'icon-mostly-cloudy',
            'mostlysunny' => 'icon-clear',
            'partlycloudy' => 'icon-mostly-cloudy',
            'partlysunny' => 'icon-clear',
            'rain' => 'icon-rain',
            'sleet' => 'icon-rain',
            'snow' => 'icon-rain',
            'sunny' => 'icon-clear',
            'tstorms' => 'icon-storm',
        ];

        $key = $forecast['styles']['foreground'];

        return $map[$key];
    }

    /**
     * Find the weather forecast for this particular day
     *
     * @param Match $match
     * @return array
     */
    private function findWeather(Match $match)
    {
        $matchDayDiff = \Alaress_Date_Format::now()->diffInDays($match->getStartAt(), false);
        $matchDay = $match->getStartAt()->getDatabaseDate();

        /* Only return data if the match hasn't already occurred
         */
        if ($matchDayDiff > 0) {

            /* Extract all the datums
             * Don't bother if the match day is too far into the future (10 days)
             */
            if ($matchDayDiff < $this->getForecastLength()) {
                $weatherController = new \Weather_Wunderground();
                $weatherController->guessLocation($match->getVenue()->getLatLong());
                $forecast = $weatherController->getForecast();

                /* Can probably find the data a little faster than spawning
                 * adf instances but there are not many to tests to conduct
                 */
                if (count($forecast['forecast'])) {
                    foreach ($forecast['forecast'] as $currentForecast) {
                        $adf = new \Alaress_Date_Format($currentForecast['datetime'], 'U');

                        if ($adf->getDatabaseDate() == $matchDay) {
                            $this->setHasResults(true);

                            return $currentForecast;
                        }
                    }
                }
            }
        }

        return [];
    }

    /* Getters/Setters
     */

    /**
     * @return Model
     */
    private function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Model $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }

    /**
     * @return int
     */
    private function getForecastLength()
    {
        return $this->forecastLength;
    }

    /**
     * @param int $forecastLength
     */
    private function setForecastLength($forecastLength)
    {
        $this->forecastLength = $forecastLength;
    }

    /**
     * @return boolean
     */
    public function hasResults()
    {
        return $this->hasResults;
    }

    /**
     * @param boolean $hasResults
     */
    private function setHasResults($hasResults)
    {
        $this->hasResults = $hasResults;
    }

    /**
     * @return string
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * @param string $condition
     */
    private function setCondition($condition)
    {
        $this->condition = $condition;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     */
    private function setIcon($icon)
    {
        $this->icon = $icon;
    }

    /**
     * @return string
     */
    public function getTemp()
    {
        return $this->temp;
    }

    /**
     * @param string $temp
     */
    private function setTemp($temp)
    {
        $this->temp = $temp;
    }
}