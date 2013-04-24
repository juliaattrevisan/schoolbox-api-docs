<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Eportfolio_ControllerAbstract extends Component_ControllerAbstract {

	/**
	 * A mapper instance, for polymorphism
	 *
	 * @var null
	 */
	protected $mapperClass = null;

	/**
	 * Apply permissions here
	 *
	 * @param array $context
	 * @param array $config
	 */
	public function setup(array $context, array $config) {
		parent::setup($context, $config);

		$this->permission = $_SESSION['su'] ? FULL_ACCESS : $context['permission'];

		/* Check primary key is being used correctly!
		 */
		$this->sitepage->setVar('ID', $context['uid']);
	}

    /**
	 * Get template
	 *
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate ($templateName = '', $templatePath = '') {
        if ( empty($templatePath) ) {
            $templatePath = "component/eportfolio";
        }

        return parent::getTemplate($templateName, $templatePath);
    }

	/* Getters/Setters
	 */

	/**
	 * @param null $mapperClass
	 */
	protected function setMapperClass($mapperClass) {
		$this->mapperClass = $mapperClass;
	}

	/**
	 * @return null
	 */
	protected function getMapperClass() {
		return $this->mapperClass;
	}
}
