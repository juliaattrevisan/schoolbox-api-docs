<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_Dashboard_Factory extends Component_FactoryAbstract {

    /**
     * @var String The module which this factory produces components for.
     */
    protected $module = 'dashboard';

    public function  __construct(\Schoolbox\User $user, SitePage $sitepage) {
        parent::__construct($user, $sitepage);
    }

}
