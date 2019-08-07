<?php
/**
 * Created by PhpStorm.
 * User: dbaker-alaress
 * Date: 21/11/18
 * Time: 2:59 PM
 */

namespace Schoolbox\Weather;

/**
 * Class ApixuClient
 * Helper class for making API calls to the Apixu weather API.
 *
 * @package Schoolbox\Weather
 */
class ApixuClient
{
    private const PROVIDER_NAME = 'Apixu';
    private const PROVIDER_URL = 'https://apixu.com';
    private const PROVIDER_LOGO = 'https://cdn.apixu.com/v2/images/logo.png';

    /*
     * @var string $apiKey
     */
    private $apiKey;

    /*
     * @var string $apiUrl
     */
    private $apiUrl;

    public function __construct(string $apiKey, string $apiUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
    }

    /**
     * Get the data from the API in a JSON format usable by Twig
     *
     * @param int $forecastLimit   How many days to forecast - configurable in component settings
     * @param string $location     City to get weather for - configurable in component settings
     *
     * @return array
     */
    public function getForecast(int $forecastLimit, string $location) : array
    {
        try {
            $response = $this->makeRequest($forecastLimit, $location);
            $twigData = $this->parseWeatherData($response);
        } catch (\Exception $e) {
            return ['weatherUnavailable' => true];
        }

        return $twigData;
    }

    /**
     * @param int $forecastLimit   How many days to forecast - configurable in component settings
     * @param string $location     City to get weather for - configurable in component settings
     *
     * @return mixed
     * @throws \DomainException
     */
    private function makeRequest(int $forecastLimit, string $location) {
        $requestUrl = "forecast.json?q={$location}&days={$forecastLimit}&key=" . $this->apiKey;

        // Cache the request
        $cache = new \Alaress_Weather_Cache();
        $cache->init($this->apiUrl . $requestUrl);
        $response = $cache->getStreamContents();

        // Attempt to json decode the response body
        try{
            $decoded_response = json_decode($response, true);
        } catch (\RuntimeException $e){
            throw new \DomainException('Unable to read Weather API response', $response, $e);
        }


        // If this is an array, we can assume we got some data back
        if (\is_array($decoded_response)) {
            return $decoded_response;
        }

        // If it isn't, it either failed to decode or we hit an incorrect server endpoint, but that server endpoint returned a 2xx, likely
        // with a HTML payload. This happens for example if you hit a login wall, which redirected you to a login page.
        throw new \DomainException("Unable to decode Weather API response: {$decoded_response}", $response);
    }


    /**
     * Given a raw JSON response from the API, turn it into a data structure we can use
     *
     * @param array $responseData
     * @return array
     */
    private function parseWeatherData($responseData) : array
    {
        $twigData = [
            'weatherLocation' => $responseData['location']['name'] . ', ' . $responseData['location']['country'],
            'apiProvider' => [
                'url' => self::PROVIDER_URL,
                'name' => self::PROVIDER_NAME,
                'logo' => self::PROVIDER_LOGO
            ]
        ];

        $todayLastUpdated = \Alaress_Date_Format::newFromTimestamp($responseData['current']['last_updated_epoch'])->getRelativeTime();

        $twigData['today'] = [
            'day' => $this->getDayOfWeek($responseData['current']['last_updated_epoch']),
            'weatherSummary' => $responseData['current']['condition']['text'],
            'weatherIcon' => $responseData['current']['condition']['icon'],
            'currentTemp' => $responseData['current']['temp_c'],
            'humidity' => $responseData['current']['humidity'],
            'windSpeed' => $responseData['current']['wind_kph'],
            'windDirection' => $responseData['current']['wind_dir'],
            'lastUpdated' => $todayLastUpdated
        ];

        foreach($responseData['forecast']['forecastday'] as $forecastDay) {
            // Get the min and max temp from today's forecast, then move on to tomorrow's forecast
            if ($this->getDayOfWeek($forecastDay['date_epoch']) === 'Now') {
                $twigData['today']['lowTemp'] = $forecastDay['day']['mintemp_c'];
                $twigData['today']['highTemp'] = $forecastDay['day']['maxtemp_c'];
                continue;
            }

            $dayForecast = [
                'day' => $this->getDayOfWeek($forecastDay['date_epoch']),
                'weatherSummary' => $forecastDay['day']['condition']['text'],
                'weatherIcon' => $forecastDay['day']['condition']['icon'],
                'lowTemp' => $forecastDay['day']['mintemp_c'],
                'highTemp' => $forecastDay['day']['maxtemp_c']
            ];

            $twigData['forecastDays'][] = $dayForecast;

        }

        // If we've reached this code path, we successfully retrieved the weather data.
        $twigData['weatherUnavailable'] = false;

        return $twigData;
    }

    /**
     * Given a unix timestamp, get a string representing the day of the week.
     *
     * @param int $timestamp
     * @return string
     */
    private function getDayOfWeek($timestamp) {
        if (\Alaress_Date_Format::newFromTimestamp($timestamp)->getDateOnly() === \Alaress_Date_Format::now()->getDateOnly()) {
            return 'Now';
        }

        $day = \Alaress_Date_Format::newFromTimestamp($timestamp)->getWeekDayNumber();

        switch($day) {
            case \Alaress_Date_Format::MONDAY:
                $dayOfWeek = 'Mon';
                break;
            case \Alaress_Date_Format::TUESDAY:
                $dayOfWeek = 'Tue';
                break;
            case \Alaress_Date_Format::WEDNESDAY:
                $dayOfWeek = 'Wed';
                break;
            case \Alaress_Date_Format::THURSDAY:
                $dayOfWeek = 'Thu';
                break;
            case \Alaress_Date_Format::FRIDAY:
                $dayOfWeek = 'Fri';
                break;
            case \Alaress_Date_Format::SATURDAY:
                $dayOfWeek = 'Sat';
                break;
            default:
                $dayOfWeek = 'Sun';
        }

        return $dayOfWeek;
    }
}