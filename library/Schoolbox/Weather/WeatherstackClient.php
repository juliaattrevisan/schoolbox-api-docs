<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Weather;

use Alaress_Date_Format;

/**
 * Class ApixuClient
 * Helper class for making API calls to the Apixu weather API.
 *
 * @package Schoolbox\Weather
 */
class WeatherstackClient
{
    private const PROVIDER_NAME = 'Apixu';
    private const PROVIDER_URL = 'https://api.weatherstack.com';
    private const PROVIDER_LOGO = 'https://cdn.apixu.com/v2/images/logo.png';
    private const NOW = 'Now';

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * ApixuClient constructor.
     *
     * @param string $apiKey
     * @param string $apiUrl
     */
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
            $requestUrl = "forecast?query={$location}&forecast_days={$forecastLimit}&units=m&access_key={$this->apiKey}";
            $response   = $this->makeRequest($requestUrl);
            $twigData   = $this->parseWeatherData($response);
        } catch (\Exception $e) {
            return ['weatherAvailable' => false];
        }

        return $twigData;
    }

    /**
     * Get weather for particular date and location
     *
     * @param Alaress_Date_Format $date
     * @param                     $latitude
     * @param                     $longitude
     *
     * @return array|mixed
     */
    public function getForecastByDate(Alaress_Date_Format $date, $latitude, $longitude)
    {
        try {
            $requestUrl = "forecast?date={$date->getDatabaseDate()}&query={$latitude},{$longitude}&units=m&access_key={$this->apiKey}";
            $response   = $this->makeRequest($requestUrl);
            if (isset($response['forecast']['forecastday'][0]['day'])) {
                return $response['forecast']['forecastday'][0]['day'];
            }
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Find $forecastLimit days worth of information about $location
     *
     * @param string $requestUrl
     * @return array
     * @throws \DomainException
     */
    private function makeRequest(string $requestUrl) : array
    {
        // Cache the request
        try {
            $cache = new \Alaress_Weather_Cache();
            $cache->init("{$this->apiUrl}/{$requestUrl}");
            $response = $cache->getStreamContents();
        } catch (\InvalidArgumentException $e) {
            throw new \DomainException('Invalid Weather API endpoint');
        }

        // Attempt to json decode the response body
        try{
            $decodedResponse = json_decode($response, true);
        } catch (\RuntimeException $e){
            throw new \DomainException('Unable to read Weather API response', $response, $e);
        }

        /* If the api wasn't correctly configured, $responseData returns an array
         * containing an error message
         */
        if (isset($decodedResponse['error'])) {
            throw new \DomainException("Weather API error: {$decodedResponse['error']['info']}");
        }


        // If this is an array, we can assume we got some data back
        if (\is_array($decodedResponse)) {
            return $decodedResponse;
        }

        // If it isn't, it either failed to decode or we hit an incorrect server endpoint, but that server endpoint returned a 2xx, likely
        // with a HTML payload. This happens for example if you hit a login wall, which redirected you to a login page.
        throw new \DomainException("Unable to decode Weather API response: {$decodedResponse}", $response);
    }


    /**
     * Given a raw JSON response from the API, turn it into a data structure we can use
     *
     * @param array $responseData
     * @return array
     */
    private function parseWeatherData(array $responseData) : array
    {
        $twigData = [
            'weatherLocation' => $responseData['location']['name'] . ', ' . $responseData['location']['country'],
            'apiProvider' => [
                'url' => self::PROVIDER_URL,
                'name' => self::PROVIDER_NAME,
                'logo' => self::PROVIDER_LOGO
            ]
        ];

        $twigData['today'] = [
            'day' => $this->getDayOfWeek($responseData['location']['localtime_epoch']),

            /* Weather descriptions and icons now come back as an array
             * But why would I get multiple of these? I just chose the first one anyway
             */
            'weatherSummary' => $responseData['current']['weather_descriptions'][0],
            'weatherIcon' => $responseData['current']['weather_icons'][0],

            /* Basic facts
             */
            'currentTemp' => $responseData['current']['temp'],
            'humidity' => $responseData['current']['humidity'],
            'windSpeed' => $responseData['current']['wind_speed'],
            'windDirection' => $responseData['current']['wind_dir'],
            'lastUpdated' => \Alaress_Date_Format::newFromTimestamp($responseData['location']['localtime_epoch'])->getRelativeTime()
        ];

        foreach ($responseData['forecast'] as $date => $data) {
            // Get the min and max temp from today's forecast, then move on to tomorrow's forecast
            if ($this->getDayOfWeek($data['date_epoch']) === self::NOW) {
                $twigData['today']['lowTemp'] = $data['mintemp'];
                $twigData['today']['highTemp'] = $data['maxtemp'];
                continue;
            }

            $dayForecast = [
                'day' => $this->getDayOfWeek($data['date_epoch']),

                /* Forecast is now broken down hourly
                 * Just get the midnight value (first in the stack)
                 * and the usual first in the stack for description/icon
                 */
                'weatherSummary' => $data['hourly'][0]['weather_descriptions'][0],
                'weatherIcon' => $data['hourly'][0]['weather_icons'][0],

                /* Basic facts
                 */
                'lowTemp' => $data['mintemp'],
                'highTemp' => $data['maxtemp']
            ];

            $twigData['forecastDays'][] = $dayForecast;

        }

        // If we've reached this code path, we successfully retrieved the weather data.
        $twigData['weatherAvailable'] = true;

        return $twigData;
    }

    /**
     * Given a unix timestamp, get a string representing the day of the week.
     *
     * @param int $timestamp
     * @return string
     */
    private function getDayOfWeek(int $timestamp): string
    {
        if (\Alaress_Date_Format::newFromTimestamp($timestamp)->isSameDay(\Alaress_Date_Format::now())) {
            return self::NOW;
        }

        return \Alaress_Date_Format::newFromTimestamp($timestamp)->getUsingFormat('D');
    }
}
