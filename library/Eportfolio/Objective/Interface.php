<?php
/**
 * Eportfolio_Objective_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Eportfolio_Objective_Interface {

	/**
	 * Render a component on the page with appropriate tokens
	 *
	 * @return void
	 */
	public function render();

	/**
	 * Render modification screen
	 *
	 * @abstract
	 * @return void
	 */
	public function renderModify();
}
