<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ClickviewController extends Component_Homepage_ControllerAbstract {

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
        if(!parent::loadTemplate($templateHandle, 'clickview')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_clickview', 'has_clickview_block');

        /* If no url is specified it might time out -_-
         * So just hide all this instead
         */
        if (strlen(trim($this->getConfig('url')))) {

            /* Pattern match the necessary code out of a url given to us
             * by the Clickview web interface
             *
             * http://webplayer.clickview.com.au/?p=kwRu&v1=home.clickview.com.au
             */
            $pattern = '/\?p=([a-zA-Z0-9]+)&/';

            $matches = array();

            if (preg_match($pattern, $this->getConfig('url'), $matches)) {
                $this->setVar(array(
                    'URL' => $matches[1],
                ));

                $this->parse('has_clickview_block', 'has_clickview');
            }
            else {
                $this->setVar('has_clickview_block', '');
            }
        }
        else {
            $this->setVar('has_clickview_block', '');
        }

        return true; // everything went according to plan
    }

}
