<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_Dashboard_DashboardFactory extends Component_AbstractFactory {

    /**
     * @var String The module which this factory produces components for.
     */
    static $location = 'dashboard';

    /**
     * @param Template $template
     * @param Integer $groupID
     */
    public function __construct(Template $template, $groupID) {
        parent::__construct($template);

        $this->setIdentifier($groupID);
    }

}