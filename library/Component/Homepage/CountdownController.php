<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_CountdownController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'countdown')) {
            return false;
        }
        $this->parseConfig();
        if (strlen($this->getConfig('date')) && strlen($this->getConfig('time'))) {
            $deadline = new Alaress_Date_Format(strtotime($this->getConfig('date') . ' ' . $this->getConfig('time')), 'U');
        }
        /* Default to now if no countdown date is specified
        * i.e. when creating component for the first time
        *
        * Use +1 seconds to offset some js rendering weirdness
        * This way it initialises at 00/00/00/00/00
        */
        else {
            $deadline = new Alaress_Date_Format(time()+1, 'U');
        }

        $this->setVar(array(
            'DEADLINE_YEAR' => $deadline->getUsingFormat('Y'),
            'DEADLINE_MONTH' => $deadline->getUsingFormat('m'),
            'DEADLINE_DAY' => $deadline->getUsingFormat('d'),
            'DEADLINE_HOUR' => $deadline->getUsingFormat('H'),
            'DEADLINE_MIN' => $deadline->getUsingFormat('i'),
            'DEADLINE_SEC' => $deadline->getUsingFormat('s'),
        ));

        return true;
    }

}
