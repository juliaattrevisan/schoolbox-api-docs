<?php
/**
 * StaffCampus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Synchronise_Maze_Loreto_Hydrator_StaffCampus implements Synchronise_Base_IHydrator {

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
		switch ($externalUser['ActiveFlag']) {
			case 'T':
				$allCampuses = array(1);
				break;

			case 'U':
				$allCampuses = array(2);
				break;

			/* Support Staff: Apply to all campuses
			 */
			case 'N':
				$allCampuses = array(1, 2);
				break;

			/* Emergency Teachers: Apply only to senior campus
			 */
			case 'E':
				$allCampuses = array(1);
				break;

			default:
				$allCampuses = array();
				break;
		}

		$newUser['campus'] = $allCampuses;
		$newUser['defaultCampus'] = 1;

		return $newUser;
	}
}

