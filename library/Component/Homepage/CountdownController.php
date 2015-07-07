<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_CountdownController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'countdown')) {
            return false;
        }
        $this->parseConfig();

        // Date/time: assume today/12:00 am respectively if not provided
        $date = $this->getConfig('date');
        $time = $this->getConfig('time');
        if (strlen($date) === 0) {
            $date = date('Y-m-d');
        }
        if (strlen($time) === 0) {
            $time = '00:00:00';
        }
        try {
            $deadline = new Alaress_Date_Format("$date $time", Alaress_Date_Format::DATABASE_FORMAT);
        } catch (Exception $e) {
            // Couldn't create a date, so default to now.
            // Use +1 seconds to offset some js rendering weirdness
            // This way it initialises at 00/00/00/00/00
            $deadline = Alaress_Date_Format::now()->addSeconds(1);
        }

        $this->setVar(
            array(
                'DEADLINE_YEAR'  => $deadline->getYear(),
                'DEADLINE_MONTH' => $deadline->getMonth(),
                'DEADLINE_DAY'   => $deadline->getDay(),
                'DEADLINE_HOUR'  => $deadline->getHour(),
                'DEADLINE_MIN'   => $deadline->getMinute(),
                'DEADLINE_SEC'   => $deadline->getSecond(),
            )
        );

        return true;
    }
}
