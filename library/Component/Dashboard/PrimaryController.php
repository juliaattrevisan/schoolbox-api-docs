<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_PrimaryController extends Component_Dashboard_ControllerAbstract {

    protected $homeroom;

    public function execute () {
        if ( is_array($_SESSION['subjects']) ) {
            foreach ( $_SESSION['subjects'] as $subject ) {
                if ( stristr($subject['code'], 'HR') || stristr($subject['code'], 'EN') ) {
                    $this->homeroom = $subject['code'];
                    break;
                }
            }
        }

        if ( empty( $this->homeroom ) ) {
            $this->homeroom = $_SESSION['subjects'][0]['code'];
        }

        return true;
    }

    public function renderTemplate ($templateHandle) {
        $this->setVar('MY_CLASS', $this->homeroom);

        return true;
    }

}