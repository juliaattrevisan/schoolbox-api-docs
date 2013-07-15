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

	/**
	 * Split this out so in ePortfolio you can determine which config elements get rendered
	 *
	 * @param $conf
	 * @param $key
	 * @param $page
	 * @param $params
	 */
	protected function parseConfigInstance($conf, $key, $page, $params) {

		/* Hide Purpose when not in admin
		 */
		if (preg_match('#^/adminv2/#', $_SERVER['REQUEST_URI'])) {
			$exclusions = array();
		}
		else {
			$exclusions = array('description');
		}

		/* Render config instance
		 */
		if (!in_array($key, $exclusions)) {
			$templateFile = "component/config/$key.html";
			$page->setFile($key, $templateFile);
			$conf->parse($page, $key, $key, Scrub::text($params['value']));

			if ($key == 'componentname') {

				/* Get the config so we have the name
				 */
				$configInstance = Component_ConfigMapper::getInstance()->findByColumnList(array(
					'component_instance_id' => $this->instance->id,
					'component_config_definition_id' => $this->instance->definition->id
				));

				if ($configInstance->count()) {
					$this->setVar(array(
						'COMPONENT_NAME' => $configInstance->first()->getValue(),
						'COMPONENT_CONFIG_NAME' => $configInstance->first()->getValue(),
					));
				}
			}

			$page->parse('CONFIG_ELEMENT_CONTENT', $key);
			$page->setVar('CONFIG_DISPLAY', $params['label'] ? '' : ' style="display:none;"');
			$page->parse('config_element_block', 'config_element', true);
		}
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
