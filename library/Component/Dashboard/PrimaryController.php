<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

class Component_Dashboard_PrimaryController extends Component_Dashboard_ControllerAbstract
{

    protected $homeroom;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if (is_array($_SESSION['subjects'])) {
            foreach ($_SESSION['subjects'] as $subject) {
                if (stristr($subject['code'], 'HR') || stristr($subject['code'], 'EN')) {
                    $this->homeroom = $subject['code'];
                    break;
                }
            }
        }

        if (empty($this->homeroom)) {
            $this->homeroom = $_SESSION['subjects'][0]['code'];
        }

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'primary')) {
            return false;
        }

        $this->setVar('MY_CLASS', $this->homeroom);

        return true;
    }
}
