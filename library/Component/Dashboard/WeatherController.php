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
    protected $weatherController;

    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        $this->weatherController = new Weather_Wunderground();
    }

    public function execute () {

        if(!$this->weatherController->guessLocation($this->getConfig('url'))) {
            return false;
        }
        if(Cfg::get('weather_enable')) {
            $this->weather = $this->weatherController->getForecast();
            return true;
        }
        return false;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'weather') ) {
            return false;
        }

        $this->sitepage->setBlock($templateHandle, 'weather_forecast', 'weather_forecast_block');
        $this->sitepage->setBlock($templateHandle, 'weather_unavailable', 'weather_unavailable_block');
        $this->sitepage->setBlock('weather_forecast', 'temp_now','temp_now_block');
        $this->sitepage->setBlock('weather_forecast', 'temp_range','temp_range_block');

        $this->sitepage->setVar('weather_forecast_block', '');
        $this->sitepage->setVar('weather_unavailable_block', '');

        if ($this->weather['location']['city']) {
            $this->renderForecast();
        } else {
            $this->sitepage->parse('weather_unavailable_block', 'weather_unavailable');
        }

        return true;
    }

    protected function renderForecast() {

        $imageLocation = '/weather/image.php?path=';

        $currentConditions = $this->weather['current'];
        $this->setVar(array(
            "FC_DAY"    => '<b>Now</b>',
            "FC_IMAGE"  => $imageLocation . parse_url($currentConditions['styles']['wxug_icon'], PHP_URL_PATH),
            "FC_PRECIS" => $currentConditions['conditions'],
            "FC_ACTUAL" => $currentConditions['temp']['actual'],
            'FC_CLASS'  => 'dark'
        ));
        $this->setVar('temp_range_block','');
        $this->parse('temp_now_block','temp_now');
        $this->parse('weather_forecast_block', 'weather_forecast', true);

        for ($i = 0; $i < 3; $i++) {
            $forecastConditions = $this->weather['forecast'][$i];
            $forecastDate = new Alaress_Date_Format($forecastConditions['datetime'], 'U');
            $this->setVar(array(
                "FC_DAY"    => $forecastDate->getUsingFormat('D'),
                "FC_IMAGE"  => $imageLocation . parse_url($forecastConditions['styles']['wxug_icon'], PHP_URL_PATH),
                "FC_PRECIS" => $forecastConditions['conditions'],
                "FC_HIGH"   => $forecastConditions['temp']['high'],
                "FC_LOW"    => $forecastConditions['temp']['low'],
                'FC_CLASS'  => ''
            ));
            $this->setVar('temp_now_block','');
            $this->parse('temp_range_block','temp_range');
            $this->parse('weather_forecast_block', 'weather_forecast', true);
        }

		$forecastDate = new Alaress_Date_Format($this->weather->weather->forecast_information->forecast_date['data'], 'y-m-d');

        $this->setvar(array(
            'WEATHER_ICON' => $this->weather->weather->current_conditions->icon['data'],
            'TEMP' => $this->weather->weather->current_conditions->temp_c['data'],
            'WIND' => substr($this->weather->weather->current_conditions->wind_condition['data'], 6),
            'HUMIDITY' => substr($this->weather->weather->current_conditions->humidity['data'], 10),
            'ISSUED' => $forecastDate->getDateOnly(),
        ));
    }

}
