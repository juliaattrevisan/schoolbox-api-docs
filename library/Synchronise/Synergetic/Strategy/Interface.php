<?php
/**
 * Standardised interface for all SynergeticSync Strategies. These are methods
 * which must exist in all SynergeticSync Strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Synchronise_Synergetic_Strategy_Interface {

	/**
	 * Get a list of records to process.
	 *
	 * @return void
	 */
	public function generateList();

	/**
	 * Process them
	 *
	 * @abstract
	 */
	public function createIfExists();
}