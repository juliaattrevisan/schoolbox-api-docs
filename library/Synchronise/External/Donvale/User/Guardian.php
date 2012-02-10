<?php
/**
 * Synchronise_External_Donvale_User_Guardian
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_External_Donvale_User_Guardian {
	private $name;
	private $homePhone;
	private $workPhone;
	private $mobilePhone;
	private $address;
	private $suburb;
	private $postcode;
	private $email;
	private $mailAddress;

	/**
	 * Set address
	 *
	 * @param $address
	 */
	public function setAddress($address) {
		$this->address = $address;
	}

	/**
	 * Get address
	 *
	 * @return mixed
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * Set email
	 *
	 * @param $email
	 */
	public function setEmail($email) {
		$this->email = $email;
	}

	/**
	 * Get email
	 *
	 * @return mixed
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * Set home phone
	 *
	 * @param $homePhone
	 */
	public function setHomePhone($homePhone) {
		$this->homePhone = $homePhone;
	}

	/**
	 * Get home phone
	 *
	 * @return mixed
	 */
	public function getHomePhone() {
		return $this->homePhone;
	}

	/**
	 * Set mail address
	 *
	 * @param $mailAddress
	 */
	public function setMailAddress($mailAddress) {
		$this->mailAddress = $mailAddress;
	}

	/**
	 * Get mail address
	 *
	 * @return mixed
	 */
	public function getMailAddress() {
		return $this->mailAddress;
	}

	/**
	 * Set mobile phone
	 *
	 * @param $mobilePhone
	 */
	public function setMobilePhone($mobilePhone) {
		$this->mobilePhone = $mobilePhone;
	}

	/**
	 * Get mobile phone
	 *
	 * @return mixed
	 */
	public function getMobilePhone() {
		return $this->mobilePhone;
	}

	/**
	 * Set postcode
	 *
	 * @param $postcode
	 */
	public function setPostcode($postcode) {
		$this->postcode = $postcode;
	}

	/**
	 * Get postcode
	 *
	 * @return mixed
	 */
	public function getPostcode() {
		return $this->postcode;
	}

	/**
	 * Set suburb
	 *
	 * @param $suburb
	 */
	public function setSuburb($suburb) {
		$this->suburb = $suburb;
	}

	/**
	 * Get suburb
	 *
	 * @return mixed
	 */
	public function getSuburb() {
		return $this->suburb;
	}

	/**
	 * Set work phone
	 *
	 * @param $workPhone
	 */
	public function setWorkPhone($workPhone) {
		$this->workPhone = $workPhone;
	}

	/**
	 * Get work phone
	 *
	 * @return mixed
	 */
	public function getWorkPhone() {
		return $this->workPhone;
	}

	/**
	 * Set name
	 *
	 * @param $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}
}
