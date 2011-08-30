<?php
/**
 * Bialik-specific methods that are provided for each synchronisation Strategy.
 *
 * You can create custom subclasses of SynergeticSyncStrategy to provide unique
 * methods to your synchronisation strategies.
 */
abstract class BialikSynergeticSyncStrategy extends SynergeticSyncStrategy {

	/**
	 * Figure out the Group ID based on a Student's year level
	 *
	 * @param  $year
	 * @return bool|int
	 */
	protected function getGroupIDFromYearLevel($year) {
		switch ($year) {
			case '02':
			case '03':
			case '04':
			case '05':
				return 8;
				break;
			case '06':
			case '07':
			case '08':
			case '09':
				return 7;
				break;
			case '10':
			case '11':
			case '12':
				return 6;
				break;
			case '00':
			case '01':
			case '30':
			case '40':
				return 9;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Figure out which Campus belongs to this Student
	 *
	 * @param  $year
	 * @return bool|string
	 */
	protected function getCampusFromYearLevel($year) {
		switch ($year) {
			case '02':
			case '03':
			case '04':
			case '05':
			case '06':
			case '07':
			case '08':
			case '09':
			case '10':
			case '11':
			case '12':
				//return 'main';
				return 1;
				break;
			case '00':
			case '01':
			case '30':
			case '40':
				//return 'elc';
				return 2;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Figure out which campus a staff member is in, based on the code provided
	 *
	 * @param  $code
	 * @return bool|string
	 */
	protected function getCampusFromCode($code) {
		switch($code) {
			case 'S':
			case 'M':
			case 'P':
				// return 'main';
				return 1;
				break;
			case 'ELC':
				// return 'elc';
				return 2;
				break;
			default:
				return false;
				break;
		}
	}
}