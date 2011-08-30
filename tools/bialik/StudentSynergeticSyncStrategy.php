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
		$data[] = "title: {$currentUser['StudentTitle']}";
		$data[] = "firstname: {$currentUser['StudentPreferred']}";
		$data[] = "surname: {$currentUser['StudentSurname']}";
		$data[] = "year: {$currentUser['StudentYearLevel']}";
		$data[] = "campus: {$this->getCampusFromYearLevel($currentUser['StudentYearLevel'])}";
		$data[] = "groupIDs: " . implode(',', unserialize(STUDENTGROUPIDS));

		$this->generateVerboseResult($data);
	}
}