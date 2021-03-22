<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

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
        $datetime = $this->getConfig('datetime');

        /**
         * If date fields are empty, or we can't parse a date out of what's entered..
         * set an empty state to show
         */
        $showEmpty = false;
        $invalidDate = false;

        $deadline = null;
        if (strlen($datetime) === 0) {
            $showEmpty = true;
        } else {
            try {
                $deadline = new Alaress_Date_Format($datetime, Alaress_Date_Format::DATABASE_FORMAT);
            } catch (Exception $e) {
                $showEmpty = true;
                $invalidDate = true;
            }
        }

        $this->setBlock($templateHandle, 'countdown', 'countdown_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if ($showEmpty) {
            if (!$this->hasContextWriteAccess()) {
                return false;
            }

            $this->setBlock('empty_state', 'empty_read_only', 'empty_read_only_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar([
                'countdown_block' => '',
                'empty_read_only_block' => '',
                'empty_config_block' => '',
                'empty_invalid_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_only_block', 'empty_read_only');
            } elseif ($invalidDate) {
                $this->parse('empty_invalid_block', 'empty_invalid');
            } else {
                $this->parse('empty_config_block', 'empty_config');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setVar('empty_state_block', '');

            // shut up storm
            if (!$deadline instanceof Alaress_Date_Format) {
                $deadline = Alaress_Date_Format::now();
            }
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
