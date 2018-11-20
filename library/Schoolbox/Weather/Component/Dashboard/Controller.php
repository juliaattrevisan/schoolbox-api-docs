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

    private const API_KEY = 'b972a53b7573b1320ef3ef0145d8e1f1';
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
        $requestUrl = "/data/2.5/forecast?q={$this->location}&units=metric&appid=" . self::API_KEY;

        try {
            $response = $this->makeRequest($requestUrl);
            $twigData = $this->parseWeatherData($response, false);
        } catch (\Exception $e) {
            $twigData = $this->parseWeatherData(['error' => $e->getMessage()], true);
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
     * @param array $data
     * @return array
     */
    private function parseWeatherData($data, $weatherUnavailable) : array
    {
        if ($weatherUnavailable) {
            return ['weatherUnavailable' => true];
        }

        $forecastData = ['weatherLocation' => $data['city']['name']];

        foreach($data['list'] as $forecastDay) {
            $weatherSummary = $forecastDay['weather'][0]['main'];
            $tempCurrent = $forecastDay['main']['temp'] ?? null;
            $tempMin = $forecastDay['main']['temp_min'];
            $tempMax = $forecastDay['main']['temp_max'];
            $humidity = $forecastDay['main']['humidity'];
            $windSpeed = $forecastDay['wind']['speed'];
            $windDegrees = $forecastDay['wind']['deg']; // TODO are we ok with this? Or should we parse it into direction format?

            // TODO parse into a unix timestamp or something. This is UTC
            $lastUpdatedDateString = $forecastDay['dt_txt'];

            $day = \Alaress_Date_Format::newFromTimestamp($forecastDay['dt'])->getWeekDayNumber();
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
            $forecastDayIsNow = \Alaress_Date_Format::now()->getUnixTimeStamp() === \Alaress_Date_Format::newFromTimestamp($forecastDay['dt'])->getUnixTimeStamp();

            $dayForecast = [
                'day' => $dayOfWeek,
                'weatherSummary' => $weatherSummary,
                'currentTemp' => $tempCurrent,
                'lowTemp' => $tempMin,
                'highTemp' => $tempMax,
                'humidity' => $humidity,
                'windSpeed' => $windSpeed,
                'windDegrees' => $windDegrees,
                'lastUpdated' => $lastUpdatedDateString,
                'weatherUnavailable' => false,
                'forecastDayIsNow' => $forecastDayIsNow,
            ];

            $forecastData['forecastDays'][] = $dayForecast;
        }

        return $forecastData;
    }

    /**
     * Given a list of 3-hourly measurements taken over a day, what is the highest recorded temperature?
     * @param array $measurements
     * @return float
     */
    private function getMaxTempForDay(array $measurements) : float
    {
        $max = 0;
        foreach($measurements as $measurement) {
            if ($measurement > $max) {
                $max = $measurement;
            }
        }
        return $max;
    }

    /**
     * Given a list of 3-hourly measurements taken over a day, what is the lowest recorded temperature?
     * @param array $measurements
     * @return float
     */
    private function getMinTempForDay(array $measurements) : float
    {
        $min = 0;
        foreach($measurements as $measurement) {
            if ($measurement < $min) {
                $min = $measurement;
            }
        }
        return $min;
    }
}
