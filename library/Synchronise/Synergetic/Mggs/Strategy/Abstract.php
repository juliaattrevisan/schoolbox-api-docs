<?php
/**
 * Mggs-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of SynergeticSyncStrategy to provide unique
 * methods to your synchronisation strategies.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Synergetic_Mggs_Strategy_Abstract extends Synchronise_Synergetic_Strategy_Abstract {
	protected $verboseFilter = null;

	/**
	 * Construct
	 *
	 * Instantiate additional classes
	 */
	public function __construct() {
		parent::__construct();

		$this->setVerboseFilter(new Synchronise_Synergetic_Mggs_Verbose());
	}
}