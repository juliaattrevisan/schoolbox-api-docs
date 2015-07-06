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
     * Component name
     * 
     * @var string
     */
    protected $componentName = 'Pinterest';

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

        $this->setBlock($templateHandle, 'has_pinterest_user', 'has_pinterest_user_block');
        $this->setBlock($templateHandle, 'has_pinterest_board', 'has_pinterest_board_block');

        $this->setVar(
            array(
                'has_pinterest_user_block'  => '',
                'has_pinterest_board_block' => '',
            )
        );

        if (strlen(trim($this->getConfig('url')))) {
            $this->setVar(
                array(
                    'PINTEREST_URL' => $this->getConfig('url')
                )
            );

            /* Determine whether it's a user profile or a board
             * Remove domain, check how may parts there are
             */
            $url = trim($this->getConfig('url'));
            $url = preg_replace('#https?://www.pinterest.com/?#', '', $url);

            $parts = array_filter(explode('/', $url));

            switch (count($parts)) {

                /* User
                 */
                case 1:
                    $this->parse('has_pinterest_user_block', 'has_pinterest_user');
                    break;

                /* Board
                 */
                case 2:
                    $this->parse('has_pinterest_board_block', 'has_pinterest_board');
                    break;

                /* Otherwise unknown
                 */
                default:
                    break;
            }
        }

        return true;
    }
} 