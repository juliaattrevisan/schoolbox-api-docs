<?php
/**
 * Synchronise_External_Donvale_User_Profile
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

/* Update sql?
 * ALTER TABLE `user_profile`     ADD COLUMN `dob` DATE NULL AFTER `user_id`,     ADD COLUMN `level` INT(20) NULL AFTER `dob`,     ADD COLUMN `homegroup` VARCHAR(255) NULL AFTER `level`,     ADD COLUMN `house` VARCHAR(255) NULL AFTER `homegroup`,     ADD COLUMN `guardian1HomePhone` VARCHAR(255) NULL AFTER `house`,     ADD COLUMN `guardian1WorkPhone` VARCHAR(255) NULL AFTER `guardian1HomePhone`,     ADD COLUMN `guardian1MobilePhone` VARCHAR(255) NULL AFTER `guardian1WorkPhone`,     ADD COLUMN `guardian1Address` VARCHAR(255) NULL AFTER `guardian1MobilePhone`,     ADD COLUMN `guardian1Suburb` VARCHAR(255) NULL AFTER `guardian1Address`,     ADD COLUMN `guardian1Postcode` VARCHAR(255) NULL AFTER `guardian1Suburb`,     ADD COLUMN `guardian1Email` VARCHAR(255) NULL AFTER `guardian1Postcode`,     ADD COLUMN `guardian1MailAddress` VARCHAR(255) NULL AFTER `guardian1Email`,     ADD COLUMN `guardian2HomePhone` VARCHAR(255) NULL AFTER `guardian1MailAddress`,     ADD COLUMN `guardian2WorkPhone` VARCHAR(255) NULL AFTER `guardian2HomePhone`,     ADD COLUMN `guardian2MobilePhone` VARCHAR(255) NULL AFTER `guardian2WorkPhone`,     ADD COLUMN `guardian2Address` VARCHAR(255) NULL AFTER `guardian2MobilePhone`,     ADD COLUMN `guardian2uburb` VARCHAR(255) NULL AFTER `guardian2Address`,     ADD COLUMN `guardian2Postcode` VARCHAR(255) NULL AFTER `guardian2uburb`,     ADD COLUMN `guardian2Email` VARCHAR(255) NULL AFTER `guardian2Postcode`,     ADD COLUMN `guardian2MailAddress` VARCHAR(255) NULL AFTER `guardian2Email`;
 */
class Synchronise_External_Donvale_User_Profile {
	private $user_id;
	private $dob;
	private $level;
	private $homegroup;
	private $house;

	/* Guardians
	 */
	private $guardian1 = null;
	private $guardian2 = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setGuardian1(new Synchronise_External_Donvale_User_Guardian());
		$this->setGuardian2(new Synchronise_External_Donvale_User_Guardian());
	}

	/**
	 * Create/update an existing profile for a particular user
	 *
	 * @return PDOStatement
	 */
	public function createUpdate() {
		$date = new Alaress_Date_Format($this->getDob(), 'd/m/y H:i:s');

		$profileData = array(
			'userId' => $this->getUserId(),
			'dob' => $date->getDatabaseDate(),
			'level' => $this->getLevel(),
			'homegroup' => $this->getHomegroup(),
			'house' => $this->getHouse(),
			'guardian1Name' => $this->getGuardian1()->getName(),
			'guardian1HomePhone' => $this->getGuardian1()->getHomePhone(),
			'guardian1WorkPhone' => $this->getGuardian1()->getWorkPhone(),
			'guardian1MobilePhone' => $this->getGuardian1()->getMobilePhone(),
			'guardian1Address' => $this->getGuardian1()->getAddress(),
			'guardian1Suburb' => $this->getGuardian1()->getSuburb(),
			'guardian1Postcode' => $this->getGuardian1()->getPostcode(),
			'guardian1Email' => $this->getGuardian1()->getEmail(),
			'guardian1MailAddress' => $this->getGuardian1()->getMailAddress(),
			'guardian2Name' => $this->getGuardian2()->getName(),
			'guardian2HomePhone' => $this->getGuardian2()->getHomePhone(),
			'guardian2WorkPhone' => $this->getGuardian2()->getWorkPhone(),
			'guardian2MobilePhone' => $this->getGuardian2()->getMobilePhone(),
			'guardian2Address' => $this->getGuardian2()->getAddress(),
			'guardian2Suburb' => $this->getGuardian2()->getSuburb(),
			'guardian2Postcode' => $this->getGuardian2()->getPostcode(),
			'guardian2Email' => $this->getGuardian2()->getEmail(),
			'guardian2MailAddress' => $this->getGuardian2()->getMailAddress(),
		);

		/* Skip ones which don't have ids
		 */
		if (is_numeric($this->getUserId())) {
			$exists = Database::getInstance()->query("
				SELECT COUNT(*) AS num
				FROM user_profile
				WHERE user_id = {$this->getUserId()}
			")->fetchColumn(0);

			if (!$exists) {
				Database::getInstance()->prepare("
					INSERT INTO user_profile (
						user_id, dob, level, homegroup, house,
						guardian1Name, guardian1HomePhone, guardian1WorkPhone, guardian1MobilePhone, guardian1Address, guardian1Suburb, guardian1Postcode, guardian1Email, guardian1MailAddress,
						guardian2Name, guardian2HomePhone, guardian2WorkPhone, guardian2MobilePhone, guardian2Address, guardian2Suburb, guardian2Postcode, guardian2Email, guardian2MailAddress
					)
					VALUES (
						:userId, :dob, :level, :homegroup, :house,
						:guardian1Name, :guardian1HomePhone, :guardian1WorkPhone, :guardian1MobilePhone, :guardian1Address, :guardian1Suburb, :guardian1Postcode, :guardian1Email, :guardian1MailAddress,
						:guardian2Name, :guardian2HomePhone, :guardian2WorkPhone, :guardian2MobilePhone, :guardian2Address, :guardian2Suburb, :guardian2Postcode, :guardian2Email, :guardian2MailAddress
					)
				")->execute($profileData);
			}
			else {
				Database::getInstance()->prepare("
					UPDATE user_profile
					SET
						dob = :dob,
						level = :level,
						homegroup = :homegroup,
						house = :house,
						guardian1Name = :guardian1Name,
						guardian1HomePhone = :guardian1HomePhone,
						guardian1WorkPhone = :guardian1WorkPhone,
						guardian1MobilePhone = :guardian1MobilePhone,
						guardian1Address = :guardian1Address,
						guardian1Suburb = :guardian1Suburb,
						guardian1Postcode = :guardian1Postcode,
						guardian1Email = :guardian1Email,
						guardian1MailAddress = :guardian1MailAddress,
						guardian2Name = :guardian2Name,
						guardian2HomePhone = :guardian2HomePhone,
						guardian2WorkPhone = :guardian2WorkPhone,
						guardian2MobilePhone = :guardian2MobilePhone,
						guardian2Address = :guardian2Address,
						guardian2Suburb = :guardian2Suburb,
						guardian2Postcode = :guardian2Postcode,
						guardian2Email = :guardian2Email,
						guardian2MailAddress = :guardian2MailAddress
					WHERE user_id = :userId
				")->execute($profileData);
			}
		}
	}

	/**
	 * Set user id
	 *
	 * @param $user_id
	 */
	public function setUserId($user_id) {
		$this->user_id = $user_id;
	}

	/**
	 * Get user id
	 *
	 * @return mixed
	 */
	private function getUserId() {
		return $this->user_id;
	}

	/**
	 * Set dob
	 *
	 * @param $dob
	 */
	public function setDob($dob) {
		$this->dob = $dob;
	}

	/**
	 * Get dob
	 *
	 * @return mixed
	 */
	private function getDob() {
		return $this->dob;
	}

	/**
	 * Set homegroup
	 *
	 * @param $homegroup
	 */
	public function setHomegroup($homegroup) {
		$this->homegroup = $homegroup;
	}

	/**
	 * Get homegroup
	 *
	 * @return mixed
	 */
	private function getHomegroup() {
		return $this->homegroup;
	}

	/**
	 * Set house
	 *
	 * @param $house
	 */
	public function setHouse($house) {
		$this->house = $house;
	}

	/**
	 * Get house
	 *
	 * @return mixed
	 */
	private function getHouse() {
		return $this->house;
	}

	/**
	 * Set level
	 *
	 * @param $level
	 */
	public function setLevel($level) {
		$this->level = $level;
	}

	/**
	 * Get level
	 *
	 * @return mixed
	 */
	private function getLevel() {
		return $this->level;
	}

	/**
	 * Set guardian 1
	 *
	 * @param $guardian1
	 */
	private function setGuardian1($guardian1) {
		$this->guardian1 = $guardian1;
	}

	/**
	 * Get guardian 1
	 *
	 * @return null
	 */
	public function getGuardian1() {
		return $this->guardian1;
	}

	/**
	 * Set guardian 2
	 *
	 * @param $guardian2
	 */
	private function setGuardian2($guardian2) {
		$this->guardian2 = $guardian2;
	}

	/**
	 * Get guardian 2
	 *
	 * @return null
	 */
	public function getGuardian2() {
		return $this->guardian2;
	}
}
