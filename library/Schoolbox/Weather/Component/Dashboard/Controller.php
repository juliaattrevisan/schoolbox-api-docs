<?php
/**
 * Controller
 *
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Weather\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\User;
use SitePage;
use GuzzleHttp;

class Controller extends Component_Dashboard_ControllerAbstract
{

    private const API_KEY = '93cc4486b7ba4afdafe44837182011';
    private $location = 'Melbourne';

    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws \InvalidArgumentException
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
    }


    /**
     * Get the data to put into the template
     *
     * @return bool
     */
    public function execute(): bool
    {
        // Return true if API call success; otherwise return false?
        return true;
    }


    /**
     * Render the component's template
     *
     * @param string $templateHandle
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        $data = $this->getForecast();
        return $this->renderTwigTemplate($templateHandle, '@weatherDashboard/component.twig', $data);
    }


    /**
     * Get a JSON representation of the data from the API
     *
     * @return array
     */
    private function getForecast() : array
    {
        $requestUrl = "http://api.apixu.com/v1/forecast.json?q={$this->location}&days=5&key=" . self::API_KEY;

        try {
            $response = $this->makeRequest($requestUrl);
            $twigData = $this->parseWeatherData($response);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'weatherUnavailable' => true];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return ['error' => $e->getMessage(), 'weatherUnavailable' => true];
        }

        return $twigData;
    }

    /**
     * @param string $requestUrl
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws \DomainException
     */
    private function makeRequest(string $requestUrl) {
        $client = new GuzzleHttp\Client(['base_uri' => 'http://api.openweathermap.org']);
        $response = $client->request('GET', $requestUrl);

        // Attempt to json decode the response body
        try{
            $decoded_response = json_decode($response->getBody()->getContents(), true);
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
     * @param array $responseData
     * @return array
     */
    private function parseWeatherData($responseData) : array
    {
        $twigData = ['weatherLocation' => $responseData['location']['name']];

        $twigData['today'] = [
            'day' => $this->getDayOfWeek($responseData['current']['last_updated_epoch']),
            'weatherSummary' => $responseData['current']['condition']['text'],
            'weatherIcon' => $responseData['current']['condition']['icon'],
            'currentTemp' => $responseData['current']['temp_c'],
            'humidity' => $responseData['current']['humidity'],
            'windSpeed' => $responseData['current']['wind_kph'],
            'windDirection' => $responseData['current']['wind_dir'],
            'lastUpdated' => $responseData['current']['last_updated_epoch'] // TODO format this
        ];

        foreach($responseData['forecast']['forecastday'] as $forecastDay) {

            // Skip today's forecast; we don't use it
            if ($this->getDayOfWeek($forecastDay['date_epoch']) === 'Now') {
                continue;
            }

            $dayForecast = [
                'day' => $this->getDayOfWeek($forecastDay['date_epoch']),
                'weatherSummary' => $forecastDay['condition']['text'],
                'weatherIcon' => $forecastDay['condition']['icon'],
                'lowTemp' => $forecastDay['min_temp_c'],
                'highTemp' => $forecastDay['max_temp_c'],
                'humidity' => $forecastDay['humidity'],
                'windSpeed' => $forecastDay['wind_kph'],
                'windDirection' => $forecastDay['wind_dir'],
                'lastUpdated' => $forecastDay['last_updated_epoch'] // TODO format this
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
