<?php

/**
 * Pinterest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_PinterestController extends Component_Homepage_ControllerAbstract
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
        if (!parent::loadTemplate($templateHandle, 'pinterest')) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_pinterest', 'has_pinterest_block');

        $this->setVar(
            array(
                'has_pinterest_block' => ''
            )
        );

        if (strlen(trim($this->getConfig('url')))) {
            $this->setVar(
                array(
                    'PINTEREST_URL' => $this->getConfig('url')
                )
            );

            $this->parse('has_pinterest_block', 'has_pinterest');
        }

        return true;
    }
} 