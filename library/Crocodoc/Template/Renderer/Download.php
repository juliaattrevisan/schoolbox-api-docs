<?php
/**
 * Download
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc\Template\Renderer;


class Download extends Base {

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inline/injectables/download.html';

	/**
	 * Render injectable
	 *
	 * @return mixed|void
	 */
	public function render() {
		$this->getPage()->setFile('injectable', $this->getTemplateFile());
		$this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');
	}
} 