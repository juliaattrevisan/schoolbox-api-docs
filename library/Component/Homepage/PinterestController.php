<?php
/**
 * Pinterest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_PinterestController extends Component_Homepage_ControllerAbstract
{
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

        $this->setBlock($templateHandle, 'pinterest', 'pinterest_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $emptyState = false;
        $noConfig = false;

        if (strlen(trim($this->getConfig('url'))) == 0) {
            $emptyState = true;
            $noConfig = true;
        } else {

            /* Determine whether it's a user profile or a board
             * Remove domain, check how may parts there are
             */
            $url = trim($this->getConfig('url'));
            $url = preg_replace('#https?://(.*).pinterest.com/?#', '', $url);

            $parts = array_filter(explode('/', $url));

            $partCount = count($parts);

            /**
             * If there's zero or more than two parts -
             * we can't handle this (most likely invalid url) so show empty state
             */
            if ($partCount != 1 && $partCount != 2) {
                $emptyState = true;
            }
        }

        if ($emptyState) {

            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_results', 'empty_results_block');
            $this->setBlock('empty_state', 'empty_results_write', 'empty_results_write_block');

            $this->setVar('pinterest_block', '');

            /**
             * If the user has no write permission just go for a generic nothing found message
             */
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {

                $this->setVar('empty_config_block', '');
                $this->setVar('empty_results_write_block', '');
                $this->parse('empty_results_block', 'empty_results');

            } else {

                $this->setVar('empty_results_block', '');

                if ($noConfig) {
                    $this->setVar('empty_results_write_block', '');
                    $this->parse('empty_config_block', 'empty_config');
                } else {
                    $this->setVar('empty_config_block', '');
                    $this->parse('empty_results_write_block', 'empty_results_write');
                }
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setBlock('pinterest', 'has_pinterest_user', 'has_pinterest_user_block');
            $this->setBlock('pinterest', 'has_pinterest_board', 'has_pinterest_board_block');

            $this->setVar([
                'empty_state_block' => '',
                'has_pinterest_user_block'  => '',
                'has_pinterest_board_block' => '',
                'PINTEREST_URL' => $this->getConfig('url')
            ]);

            switch ($partCount) {

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

            $this->parse('pinterest_block', 'pinterest');

        }

        return true;
    }
} 