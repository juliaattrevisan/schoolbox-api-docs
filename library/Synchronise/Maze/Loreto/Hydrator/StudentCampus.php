<?php
/**
 * StudentCampus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Maze_Loreto_Hydrator_StudentCampus implements Synchronise_Base_IHydrator {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * {@inheritdoc}
	 */
	public function hydrate($externalUser, $newUser) {

		/* Senior: 1
		 * Junior: 2
		 */
		switch ($externalUser['StudentYearLevel']) {
			case 7:
			case 8:
			case 9:
			case 10:
			case 11:
			case 12:
				$allCampuses = array(1);
				break;

			default:
				$allCampuses = array(2);
				break;
		}

		$newUser['campus'] = $allCampuses;
		$newUser['defaultCampus'] = 1;

		return $newUser;
	}
}

