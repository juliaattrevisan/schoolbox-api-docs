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
		$this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		foreach($this->getList() as $currentUser) {

			/* The drop down in Synergetic's Phone and Occupation tabs indicates
			 * which email the person prefers to use. Create a field in the
			 * $currentUser structure to remember this email address
			 */
			if ($currentUser['DefaultEmailCode'] == 'O') {
				$currentUser['preferredEmail'] = $currentUser['OccupEmail'];
			}
			else {
				$currentUser['preferredEmail'] = $currentUser['Email'];
			}

			/* TODO: Do db create here
			 */
			$this->verboseResult($currentUser);
		}
	}

	/**
	 * Verbose feedback on what just happened
	 *
	 * @param  $currentUser
	 * @return void
	 */
	private function verboseResult($currentUser) {
		$data = array();
		$data[] = "username: {$currentUser['NetworkLogin']}";
		$data[] = "password: unknown";
		$data[] = "title: {$currentUser['StaffTitle']}";
		$data[] = "firstname: {$currentUser['StaffPreferredName']}";
		$data[] = "surname: {$currentUser['StaffSurname']}";
		$data[] = "email: {$currentUser['preferredEmail']}";
		$data[] = "campus: {$this->getCampusFromCode($currentUser['StaffCampus'])}";
		$data[] = "groupIDs: " . implode(',', unserialize(STAFFGROUPIDS));

		$this->generateVerboseResult($data);
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