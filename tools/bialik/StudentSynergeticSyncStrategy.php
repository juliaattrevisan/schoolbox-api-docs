<?php
/**
 * Adds Students in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 */
class StudentSynergeticSyncStrategy extends SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $strategyTitle = "Student Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$allUsers = $this->getExternalUserHandler()->getStudentUserAccountInfo();

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
			$data[] = "username: {$currentUser['NetworkLogin']}";
			$data[] = "password: unknown";
			$data[] = "title: {$currentUser['StudentTitle']}";
			$data[] = "firstname: {$currentUser['StudentPreferred']}";
			$data[] = "surname: {$currentUser['StudentSurname']}";
			$data[] = "year: {$currentUser['StudentYearLevel']}";
			$data[] = "campus: {$this->getCampusFromYearLevel($currentUser['StudentYearLevel'])}";
			$data[] = "groupIDs: " . implode(',', unserialize(STUDENTGROUPIDS));

			$str = "$action [" . implode(', ', $data) . "]\n";

			print_r($str);
		}
	}

}