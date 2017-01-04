<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_CountdownController extends Component_Homepage_ControllerAbstract
{
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

        /**
         * Get date & time set in config
         */
        $date = $this->getConfig('date');
        $time = $this->getConfig('time');

        /**
         * If a date has been set but no time - default time to 12:00am
         */
        if (strlen($date) !== 0 && strlen($time) === 0) {
            $time = '00:00:00';
        }

        /**
         * If date fields are empty, or we can't parse a date out of what's entered..
         * set an empty state to show
         */
        $showEmpty = false;
        $invalidDate = false;

        if (strlen($date) === 0 && strlen($time) === 0) {
            $showEmpty = true;
        } else {
            try {
                $deadline = new Alaress_Date_Format($date . ' ' . $time, Alaress_Date_Format::DATABASE_FORMAT);
            } catch (Exception $e) {
                $showEmpty = true;
                $invalidDate = true;
            }
        }

        $this->setBlock($templateHandle, 'countdown', 'countdown_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if ($showEmpty) {

            $this->setVar('countdown_block', '');
            $this->setBlock('empty_state', 'empty_read_only', 'empty_read_only_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar([
                'empty_read_only_block' => '',
                'empty_config_block' => '',
                'empty_invalid_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_only_block', 'empty_read_only');
            } else if ($invalidDate) {
                $this->parse('empty_invalid_block', 'empty_invalid');
            } else {
                $this->parse('empty_config_block', 'empty_config');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setVar('empty_state_block', '');

            $this->setVar([
                'DEADLINE_YEAR'  => $deadline->getYear(),
                'DEADLINE_MONTH' => $deadline->getMonth(),
                'DEADLINE_DAY'   => $deadline->getDay(),
                'DEADLINE_HOUR'  => $deadline->getHour(),
                'DEADLINE_MIN'   => $deadline->getMinute(),
                'DEADLINE_SEC'   => $deadline->getSecond(),
            ]);

            $this->parse('countdown_block', 'countdown');
        }

        return true;
    }
}
