<?php
/**
 * ParentEmail
 *
 * Decide which of the two family email addresses to use
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Maze_Loreto_Hydrator_ParentEmail implements Synchronise_Base_IHydrator {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * {@inheritdoc}
	 */
	public function hydrate($externalUser, $newUser) {
		$newUser['alt_email'] = $externalUser['StudentContactEmail'];

		return $newUser;
	}
}

