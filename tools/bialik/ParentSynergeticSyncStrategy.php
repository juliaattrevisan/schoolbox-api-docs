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
		$this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
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
		$data[] = "username: {$currentUser['StudentContactID']}";
		$data[] = "password: unknown";
		$data[] = "title: {$currentUser['StudentContactTitle']}";
		$data[] = "firstname: {$currentUser['StudentContactPreferred']}";
		$data[] = "surname: {$currentUser['StudentContactSurname']}";
		$data[] = "email: {$currentUser['StudentContactDefaultEmail']}";
		$data[] = "campus: {$this->getCampusFromYearLevel($currentUser['StudentYearLevel'])}";
		$data[] = "groupIDs: " . implode(',', unserialize(PARENTGROUPIDS));

		$this->generateVerboseResult($data);
	}
}