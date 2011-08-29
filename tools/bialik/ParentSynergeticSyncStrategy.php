<?php
/**
 * Adds Parents in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 */
class ParentSynergeticSyncStrategy extends SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $strategyTitle = "Parent Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$allUsers = $this->getExternalUserHandler()->getParentUserAccountInfo();

		$this->collateList($allUsers);
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		foreach($this->getList() as $currentUser) {
			if ($this->getPerformUpdate() === true) {

				/* TODO: Do db create here
				 */

				$action = "Created account for:  ";
			}
			else {
				$action = "Planned creation of:  ";
			}

			$data = array();
			$data[] = "username: {$currentUser['StudentContactID']}";
			$data[] = "password: unknown";
			$data[] = "title: {$currentUser['StudentContactTitle']}";
			$data[] = "firstname: {$currentUser['StudentContactPreferred']}";
			$data[] = "surname: {$currentUser['StudentContactSurname']}";
			$data[] = "email: {$currentUser['StudentContactDefaultEmail']}";
			$data[] = "campus: {$this->getCampusFromYearLevel($currentUser['StudentYearLevel'])}";
			$data[] = "groupIDs: " . implode(',', unserialize(PARENTGROUPIDS));

			$str = "$action [" . implode(', ', $data) . "]\n";

			print_r($str);
		}
	}

	/**
	 * Figure out which Campus belongs to this Student
	 *
	 * @param  $year
	 * @return bool|string
	 */
	public function getCampusFromYearLevel($year) {
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
				return 'main';
				break;
			case '00':
			case '01':
			case '30':
			case '40':
				return 'elc';
				break;
			default:
				return false;
				break;
		}
	}
}