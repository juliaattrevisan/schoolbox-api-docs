<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_WenonaIntranetController extends Component_Dashboard_ControllerAbstract {

    public function execute () {
        return true;
    }

    public function renderTemplate ($templateHandle) {
        $this->loadTemplate($templateHandle, 'empty');

        return true;
    }

}