<?php
/**
 * Adds Staff in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Staff's campus field.
 */
class StaffSynergeticSyncStrategy extends BialikSynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
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

			/* Database update
			 */
			if ($this->getPerformUpdate() === true) {
				$newUser = array();

				/* Login details
				 */
				$newUser['username'] = $currentUser['NetworkLogin'];
				$newUser['password'] = '1234';

				/* User details
				 */
				$newUser['title'] = $currentUser['StaffTitle'];
				$newUser['firstname'] = $currentUser['StaffPreferredName'];
				$newUser['surname'] = $currentUser['StaffSurname'];
				$newUser['email'] = $currentUser['preferredEmail'];

				/* Campus config: needs to map to a campus id
				 */
				$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

				/* Staff Group ID
				 */
				$newUser['group_id'] = 2;

				if ($this->getVerbose() === true) {
//					print_r($newUser);
				}
			}

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
		if ($this->getVerbose() === true) {
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
	}
}