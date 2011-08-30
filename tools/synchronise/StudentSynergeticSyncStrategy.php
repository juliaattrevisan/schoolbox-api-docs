<?php
/**
 * Adds Students in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 */
class StudentSynergeticSyncStrategy extends BialikSynergeticSyncStrategy {
	protected $strategyTitle = "Student Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStudentUserAccountInfo());
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		foreach($this->getList() as $currentUser) {

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
				$newUser['title'] = $currentUser['StudentTitle'];
				$newUser['firstname'] = $currentUser['StudentPreferred'];
				$newUser['surname'] = $currentUser['StudentSurname'];
				$newUser['year'] = $currentUser['StudentYearLevel'];

				/* Campus config: needs to map to a campus id
				 */
				$newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

				/* Student Group ID
				 */
				$newUser['group_id'] = 3;

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
			$data[] = "title: {$currentUser['StudentTitle']}";
			$data[] = "firstname: {$currentUser['StudentPreferred']}";
			$data[] = "surname: {$currentUser['StudentSurname']}";
			$data[] = "year: {$currentUser['StudentYearLevel']}";
			$data[] = "campus: {$this->getCampusFromYearLevel($currentUser['StudentYearLevel'])}";
			$data[] = "group_id: " . $this->getGroupIDFromYearLevel($currentUser['StudentYearLevel']);

			$this->generateVerboseResult($data);
		}
	}
}