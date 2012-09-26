<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_AdspaceController extends Component_Dashboard_ControllerAbstract {

    private $adspace;

    public function execute () {

        // define the 3 elements here
        $adspace = array(
            'image' => '/images/mggs/adspace/news-image.jpg',
            'title' => 'Science Week',
            'detail' => '12th May - 17th May'
        );

        // namespace the vars for the template
        foreach($adspace as $k => $v) {
            $this->adspace['ADSPACE_'.strtoupper($k)] = $v;
        }

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'adspace') ) {
            return false;
        }

        $this->setVar($this->adspace);
        return true;
    }

}