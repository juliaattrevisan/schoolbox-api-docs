<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day\Renderer;

class Html extends Base {


    /**
     * Render this event as html using twig
     * @return string
     */
    public function render(): string
    {

        $returnUrl = $_SERVER["REQUEST_URI"];
        $path = explode('/', $returnUrl);
        $returnStr = "&returnTo=";
        if ($returnUrl == "/") {
            $returnStr .= 'dashboard';
        }
        elseif ($path[1] == 'homepage' && intval($path[2]) > 0){
            $returnStr .= "homepage&homepageId=".$path[2];
        }
        elseif ($path[1] == "calendar") {
            if($path[2] == "full") {
                $returnStr .= "calendar";
            }
            elseif($path[2] == "list") {
                $returnStr .= "list&userId=".$path[3]."&returnDate=".$path[4];
            }
        }
        elseif (isset($this->returnStr)) {
            $returnStr .= $this->returnStr;
        }
        //Return the URL as is
        else {
            $returnStr .= urlencode($returnUrl);
        }

        return $this->getTemplate()->render('calendar/type/day.twig',
            array(  'event' => $this->getEvent(),
                    'renderer' => $this,
                    'returnStr' => $returnStr,
            )
        );
    }
}
