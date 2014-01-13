<?php
/**
 * None
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc\Renderer\Strategy;

class None extends Base {

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inline/injectables/views/none.html';

	/**
	 * Control file, to determine how to return work
	 *
	 * @var string
	 */
	protected $controlFile = 'submission/inline/injectables/controls/redirect-return-control.html';

	/**
	 * Render injectable
	 *
	 * @return mixed|void
	 */
	public function render() {
		$this->getPage()->setFile('injectable', $this->getTemplateFile());
		$this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

		/* Upload file
		 */
		$this->getPage()->setVar(array(
			'INJECTED_MARK_TEMPLATE' => ''
		));

		/* Control how Return Work is done
		 */
		$this->getPage()->setFile('injectableControl', $this->getControlFile());

		$this->getPage()->setVar(array(
			'INJECTABLE_CONTROL_NEXT_URL' => $this->getNextUrl(),
		));

		$this->getPage()->parse('INJECTED_INTERFACE_CONTROL', 'injectableControl');
	}
}