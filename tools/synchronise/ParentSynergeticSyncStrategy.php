<?php
/**
 * Adds Parents in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 */
class ParentSynergeticSyncStrategy extends BialikSynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
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

			/* Database update
			 */
			if ($this->getPerformUpdate() === true) {
				$newUser = array();

				/* Login details
				 */
				$newUser['username'] = $currentUser['StudentContactID'];
				$newUser['password'] = '1234';

				/* User details
				 */
				$newUser['title'] = $currentUser['StudentContactTitle'];
				$newUser['firstname'] = $currentUser['StudentContactPreferred'];
				$newUser['surname'] = $currentUser['StudentContactSurname'];
				$newUser['email'] = $currentUser['StudentContactDefaultEmail'];

				/* Campus config: needs to map to a campus id
				 */
				$newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

				/* Parent Group ID
				 */
				$newUser['group_id'] = 4;

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
}