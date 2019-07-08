<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 *
 */
class Component_Dashboard_Factory extends Component_FactoryAbstract
{
    public function __construct(\Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($user, $sitepage);
        $this->setModule('dashboard');
    }
}
