<?php
/**
 * Adds Staff in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Staff's campus field.
 */
class StaffSynergeticSyncStrategy extends SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $strategyTitle = "Staff Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$allUsers = $this->getExternalUserHandler()->getStaffUserAccountInfo();

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
			/* The drop down in synergetic's Phone and Occupation tabs indicates
			 * which email the person prefers to use.
			 */
			if ($currentUser['DefaultEmailCode'] == 'O') {
				$preferredEmail = $currentUser['OccupEmail'];
			}
			else {
				$preferredEmail = $currentUser['Email'];
			}

			if ($this->getPerformUpdate() === true) {

				/* TODO: Do db create here
				 */

				$action = "Created account for:  ";
			}
			else {
				$action = "Planned creation of:  ";
			}

			$data = array();
			$data[] = "username: {$currentUser['NetworkLogin']}";
			$data[] = "password: unknown";
			$data[] = "title: {$currentUser['StaffTitle']}";
			$data[] = "firstname: {$currentUser['StaffPreferredName']}";
			$data[] = "surname: {$currentUser['StaffSurname']}";
			$data[] = "email: {$preferredEmail}";
			$data[] = "campus: {$this->getCampusFromCode($currentUser['StaffCampus'])}";
			$data[] = "groupIDs: " . implode(',', unserialize(STAFFGROUPIDS));

			$str = "$action [" . implode(', ', $data) . "]\n";

			print_r($str);
		}
	}

	/**
	 * Figure out which campus a staff member is in, based on the code provided
	 * @param  $code
	 * @return bool|string
	 */
	private function getCampusFromCode($code) {

		/* TODO: Finalise these
		 */
		switch($code) {
			case 'S':
			case 'M':
			case 'P':
				return 'main';
				break;
			case 'ELC':
				return 'elc';
				break;
			default:
				return false;
				break;
		}
	}
}