<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_WeatherController extends Component_Dashboard_ControllerAbstract {

    protected $weather;

    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        $this->weather = new Xml('http://www.google.com/ig/api?weather=' . urlencode(Cfg::get('weather_location')));
    }

    public function execute () {
        if(Cfg::get('weather_enable')) {
            return true;
        }
        else {
            return false;
        }
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'weather') ) {
            return false;
        }

        $this->sitepage->setBlock($templateHandle, 'weather_forecast', 'weather_forecast_block');
        $this->sitepage->setBlock($templateHandle, 'weather_unavailable', 'weather_unavailable_block');

        $this->sitepage->setVar('weather_forecast_block', '');
        $this->sitepage->setVar('weather_unavailable_block', '');

        if ($this->weather->weather->forecast_conditions) {
            $this->renderForecast();
            $this->sitepage->parse('weather_forecast_block', 'weather_forecast');
        } else {
            $this->sitepage->parse('weather_unavailable_block', 'weather_unavailable');
        }

        return true;
    }

    protected function renderForecast() {
        $imageLocation = '/weather/image.php?path=';

        for ($i = 0; $i < 3; $i++) {
            $forecast_conditions = $this->weather->weather->forecast_conditions[$i];
            $url = parse_url((string)$forecast_conditions->icon['data']);
            $this->setVar(array(
                "DAY$i" => $forecast_conditions->day_of_week['data'],
                "IMAGE$i" => $imageLocation . urlencode($url['path']),
                "PRECIS$i" => $forecast_conditions->condition['data'],
                "HIGH$i" => round(($forecast_conditions->high['data'] - 32) / 1.8),
                "LOW$i" => round(($forecast_conditions->low['data'] - 32) / 1.8)
            ));
        }

		$forecastDate = new Alaress_Date_Format($this->weather->weather->forecast_information->forecast_date['data'], 'y-m-d');

        $this->setvar(array(
            'WEATHER_ICON' => $imageLocation . $this->weather->weather->current_conditions->icon['data'],
            'TEMP' => $this->weather->weather->current_conditions->temp_c['data'],
            'WIND' => substr($this->weather->weather->current_conditions->wind_condition['data'], 6),
            'HUMIDITY' => substr($this->weather->weather->current_conditions->humidity['data'], 10),
            'ISSUED' => $forecastDate->getDateOnly(),
        ));
    }

}
