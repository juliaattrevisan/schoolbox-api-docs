<?php
/**
 * Lovecraft
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class Lovecraft {

	/**
	 * The pad to respawn, from the local db
	 *
	 * @var null
	 */
	private $pad = null;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Reincarnate an EPL instance of a pad that exists in our local db
	 *
	 * @throws \Exception
	 */
	public function respawn($pad) {

		/* Remember the pad we tried to respawn
		 */
		$this->setPad($pad);

		/* Check that you have write permissions
		 */
		$authenticator = new \Etherpad\Authenticator();

		if ($authenticator->canCreate($this->getHomepageId())) {

			/* Make life a bit easier for Etherpad by using a hashed version
			 * of the name, rather than let it get urlencoded in some strange
			 * way by Etherpad itself
			 *
			 * Use an INSTANCE_ID value as a prefix, to help prevent namespace
			 * collisions when generating $padName values (in case someone else
			 * uses the same name as you - etherpad services are shared between
			 * the live and dev environments, so we use INSTANCE_ID to keep 'em
			 * separated to some extent)
			 */
			$padName = md5(INSTANCE_ID . '_' . $this->getPad()->getName());

			/* Create a new group pad
			 */
			$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

			/* Create a new pad, get group id
			 * Create a group pad
			 * Spawn a new group of users
			 * Assign them to a group
			 */
			$groupResult = $client->createGroup();

			if ($groupResult->getMessage() == 'ok') {
				$data = $groupResult->getData();
				$groupId = $data['groupID'];

				/* Create a group pad
				 */
				$createResult = $client->createGroupPad($groupId, $padName);

				if ($createResult->getMessage() == 'ok') {

					/* Get pad id to set to private and to pass it as a redirect element.
					 * Note that padId's come in the form g.abcdefg$padName
					 */
					$data = $createResult->getData();
					$padId = $data['padID'];

					/* Save new padId
					 */
					$padMapper = new \Etherpad\PadMapper();
					$this->getPad()->setPadId($padId);
					$padMapper->save($this->getPad());

					/* Store the local db's body text into the newly-spawned
					 * etherpad/redis instance
					 */
					$client->setText($padId, $pad->getBody());

					return $this->getPad();
				}
				else {
					throw new \Exception('Something strange happened when creating a new pad');
				}
			}
			else {
				throw new \Exception('Something strange happened when creating a group id');
			}
		}
		else {
			error(19);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set pad
	 *
	 * @param null $pad
	 */
	private function setPad($pad) {
		$this->pad = $pad;
	}

	/**
	 * Get pad
	 *
	 * @return null
	 */
	private function getPad() {
		return $this->pad;
	}


}