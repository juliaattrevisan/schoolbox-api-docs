<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_Factory extends Component_FactoryAbstract {

    /**
     * @var String The module for which this factory produces components.
     */
    protected $module = 'eportfolio';

	public function __construct(User $user, SitePage $sitepage, $template = false) {
		$this->module = $template ? 'eportfolio_template' : 'eportfolio';

		parent::__construct($user, $sitepage);
	}
}
