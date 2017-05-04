<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_Factory extends Component_FactoryAbstract
{
    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param SitePage $sitepage
     * @param bool $template
     */
    public function __construct(\Schoolbox\User $user, SitePage $sitepage, $template = false)
    {
        parent::__construct($user, $sitepage);
        $this->setModule($template ? 'eportfolio_template' : 'eportfolio');
    }
}
