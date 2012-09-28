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

/*
        // this relies on 12.5.0 schema, and will require dashboard component configurability
        // $textBoxID = $this->getConfig('textbox');
        $textBoxID = 267;

        $tboxHandler = new TextBox();
        $tbox = $tboxHandler->getByID($textBoxID);
        // define the 3 elements here
        $adspace = array(
            // 'image' => '/images/mggs/adspace/news-image.jpg',
            'title' => $tbox['heading'],
            'content' => $tbox['body']
    );
*/

        $adspace = array(
            'image' => '/images/mggs/adspace/news-image.jpg',
            'title' => 'Science Week',
            'content' => '<h3>Science Week</h3><p><span class="dates">12th August - 17th August</span></p>'
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

        $this->setBlock($templateHandle, 'assoc_image', 'assoc_image_block');

        $this->setVar($this->adspace);

        if(isset($this->adspace['ADSPACE_IMAGE'])) {
            $this->parse('assoc_image_block', 'assoc_image');
        }
        else {
            $this->setVar('assoc_image_block', '');
        }

        return true;
    }

}