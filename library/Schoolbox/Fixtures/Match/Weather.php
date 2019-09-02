<?php
/**
 * Weather
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Match;

use \Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Weather\ApixuClient;

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
     * The weather API
     *
     * @var ApixuClient
     */
    private $api;

    /**
     * Weather constructor.
     *
     * @param Match       $match
     * @param ApixuClient $api
     */
    public function __construct(Match $match, ApixuClient $api)
    {
        $this->setMatch($match);
        $this->api = $api;

        $this->findForecast();
    }

    /**
     * Find the forecast
     *
     */
    private function findForecast()
    {
        $forecast = $this->findWeather($this->getMatch());
        if (!empty($forecast)) {
            $this->setIcon($forecast['condition']['icon']);
            $this->setCondition($forecast['condition']['text']);
            $this->setTemp($forecast['avgtemp_c']);
        }
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
        $matchDay     = $match->getStartAt()->getDatabaseDate();

        /* Only return data if the match hasn't already occurred
         */
        if ($matchDayDiff > 0) {

            /* Extract all the datums
             * Don't bother if the match day is too far into the future (10 days)
             */
            if ($matchDayDiff < $this->getForecastLength()) {
                $forecast = $this->api->getForecastByDate($matchDay, $match->getVenue()->getLatitude(), $match->getVenue()->getLongitude());
                if (!empty($forecast)) {
                    $this->setHasResults(true);
                }
                return $forecast;
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
