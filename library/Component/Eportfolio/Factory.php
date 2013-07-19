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

	/**
	 * Constructor
	 *
	 * @param User $user
	 * @param SitePage $sitepage
	 * @param bool $template
	 */
	public function __construct(User $user, SitePage $sitepage, $template = false) {
		$this->module = $template ? 'eportfolio_template' : 'eportfolio';

		parent::__construct($user, $sitepage);
	}

	/**
	 * Return a collection of components used in any eportfolio template setup
	 *
	 * @param string $module
	 * @return \Schoolbox\Collection
	 */
	public function getDefinitionList($module) {
		return new Schoolbox\Collection(Component_InstanceMapper::getInstance()->findByModule($module));
	}
}
