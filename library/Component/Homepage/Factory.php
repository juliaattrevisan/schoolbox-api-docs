<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Homepage_Factory extends Component_FactoryAbstract {

    /**
     * @var String The module for which this factory produces components.
     */
    protected $module;

    public function __construct(\Schoolbox\User $user, SitePage $sitepage, $template = false) {
        $this->module = $template ? 'homepage_template' : 'homepage';

        parent::__construct($user, $sitepage);
    }

}
