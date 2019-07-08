<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Homepage_Factory extends Component_FactoryAbstract
{
    public function __construct(\Schoolbox\User $user, SitePage $sitepage, $template = false)
    {
        parent::__construct($user, $sitepage);
        $this->setModule($template ? 'homepage_template' : 'homepage');
    }
}
