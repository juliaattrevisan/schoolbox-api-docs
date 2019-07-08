<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
