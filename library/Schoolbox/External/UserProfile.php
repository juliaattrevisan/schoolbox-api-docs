<?php
/**
 * User profile object for Schoolbox.
 *
 * Will eventually be associated with a category.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\External;

class UserProfile {
    /**
     * @var int
     */
    protected $user_id;
    
    /**
     * @var \Schoolbox\User
     */
    protected $user;

    /**
     * @var \Alaress_Date_Format
     */
    protected $dob;

    /**
     * @var string
     */
    protected $level;

    /**
     * @var string
     */
    protected $homegroup;

    /**
     * @var string
     */
    protected $house;

    /**
     * @var string
     */
    protected $guardian1_name;

    /**
     * @var string
     */
    protected $guardian1_home_phone;

    /**
     * @var string
     */
    protected $guardian1_work_phone;

    /**
     * @var string
     */
    protected $guardian1_mobile_phone;

    /**
     * @var string
     */
    protected $guardian1_address;

    /**
     * @var string
     */
    protected $guardian1_suburb;

    /**
     * @var string
     */
    protected $guardian1_postcode;

    /**
     * @var string
     */
    protected $guardian1_email;

    /**
     * @var string
     */
    protected $guardian1_mail_address;

    /**
     * @var string
     */
    protected $guardian2_name;

    /**
     * @var string
     */
    protected $guardian2_home_phone;

    /**
     * @var string
     */
    protected $guardian2_work_phone;

    /**
     * @var string
     */
    protected $guardian2_mobile_phone;

    /**
     * @var string
     */
    protected $guardian2_address;

    /**
     * @var string
     */
    protected $guardian2_suburb;

    /**
     * @var string
     */
    protected $guardian2_postcode;

    /**
     * @var string
     */
    protected $guardian2_email;

    /**
     * @var string
     */
    protected $guardian2_mail_address;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $given_name;

    /**
     * @var string
     */
    protected $preferred_name;

    /**
     * @var string
     */
    protected $last_name;

    /**
     * @var string
     */
    protected $gender;

    /**
     * @var string
     */
    protected $email;

    /**
     * @param  \Alaress_Date_Format $dob
     * @return $this
     */
    public function setDob($dob) {
        $this->dob = $dob;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDob() {
        return $this->dob;
    }

    /**
     * @param  string $email
     * @return $this
     */
    public function setEmail($email) {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

    /**
     * @param  string $gender
     * @return $this
     */
    public function setGender($gender) {
        $this->gender = $gender;
        return $this;
    }

    /**
     * @return string
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     * @param  string $given_name
     * @return $this
     */
    public function setGivenName($given_name) {
        $this->given_name = $given_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getGivenName() {
        return $this->given_name;
    }

    /**
     * @param  string $guardian1_address
     * @return $this
     */
    public function setGuardian1Address($guardian1_address) {
        $this->guardian1_address = $guardian1_address;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1Address() {
        return $this->guardian1_address;
    }

    /**
     * @param  string $guardian1_email
     * @return $this
     */
    public function setGuardian1Email($guardian1_email) {
        $this->guardian1_email = $guardian1_email;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1Email() {
        return $this->guardian1_email;
    }

    /**
     * @param  string $guardian1_home_phone
     * @return $this
     */
    public function setGuardian1HomePhone($guardian1_home_phone) {
        $this->guardian1_home_phone = $guardian1_home_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1HomePhone() {
        return $this->guardian1_home_phone;
    }

    /**
     * @param  string $guardian1_mail_address
     * @return $this
     */
    public function setGuardian1MailAddress($guardian1_mail_address) {
        $this->guardian1_mail_address = $guardian1_mail_address;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1MailAddress() {
        return $this->guardian1_mail_address;
    }

    /**
     * @param  string $guardian1_mobile_phone
     * @return $this
     */
    public function setGuardian1MobilePhone($guardian1_mobile_phone) {
        $this->guardian1_mobile_phone = $guardian1_mobile_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1MobilePhone() {
        return $this->guardian1_mobile_phone;
    }

    /**
     * @param  string $guardian1_name
     * @return $this
     */
    public function setGuardian1Name($guardian1_name) {
        $this->guardian1_name = $guardian1_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1Name() {
        return $this->guardian1_name;
    }

    /**
     * @param  string $guardian1_postcode
     * @return $this
     */
    public function setGuardian1Postcode($guardian1_postcode) {
        $this->guardian1_postcode = $guardian1_postcode;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1Postcode() {
        return $this->guardian1_postcode;
    }

    /**
     * @param  string $guardian1_suburb
     * @return $this
     */
    public function setGuardian1Suburb($guardian1_suburb) {
        $this->guardian1_suburb = $guardian1_suburb;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1Suburb() {
        return $this->guardian1_suburb;
    }

    /**
     * @param  string $guardian1_work_phone
     * @return $this
     */
    public function setGuardian1WorkPhone($guardian1_work_phone) {
        $this->guardian1_work_phone = $guardian1_work_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian1WorkPhone() {
        return $this->guardian1_work_phone;
    }

    /**
     * @param  string $guardian2_address
     * @return $this
     */
    public function setGuardian2Address($guardian2_address) {
        $this->guardian2_address = $guardian2_address;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2Address() {
        return $this->guardian2_address;
    }

    /**
     * @param  string $guardian2_email
     * @return $this
     */
    public function setGuardian2Email($guardian2_email) {
        $this->guardian2_email = $guardian2_email;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2Email() {
        return $this->guardian2_email;
    }

    /**
     * @param  string $guardian2_home_phone
     * @return $this
     */
    public function setGuardian2HomePhone($guardian2_home_phone) {
        $this->guardian2_home_phone = $guardian2_home_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2HomePhone() {
        return $this->guardian2_home_phone;
    }

    /**
     * @param  string $guardian2_mail_address
     * @return $this
     */
    public function setGuardian2MailAddress($guardian2_mail_address) {
        $this->guardian2_mail_address = $guardian2_mail_address;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2MailAddress() {
        return $this->guardian2_mail_address;
    }

    /**
     * @param  string $guardian2_mobile_phone
     * @return $this
     */
    public function setGuardian2MobilePhone($guardian2_mobile_phone) {
        $this->guardian2_mobile_phone = $guardian2_mobile_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2MobilePhone() {
        return $this->guardian2_mobile_phone;
    }

    /**
     * @param  string $guardian2_name
     * @return $this
     */
    public function setGuardian2Name($guardian2_name) {
        $this->guardian2_name = $guardian2_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2Name() {
        return $this->guardian2_name;
    }

    /**
     * @param  string $guardian2_postcode
     * @return $this
     */
    public function setGuardian2Postcode($guardian2_postcode) {
        $this->guardian2_postcode = $guardian2_postcode;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2Postcode() {
        return $this->guardian2_postcode;
    }

    /**
     * @param  string $guardian2_suburb
     * @return $this
     */
    public function setGuardian2Suburb($guardian2_suburb) {
        $this->guardian2_suburb = $guardian2_suburb;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2Suburb() {
        return $this->guardian2_suburb;
    }

    /**
     * @param  string $guardian2_work_phone
     * @return $this
     */
    public function setGuardian2WorkPhone($guardian2_work_phone) {
        $this->guardian2_work_phone = $guardian2_work_phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getGuardian2WorkPhone() {
        return $this->guardian2_work_phone;
    }

    /**
     * @param  string $homegroup
     * @return $this
     */
    public function setHomegroup($homegroup) {
        $this->homegroup = $homegroup;
        return $this;
    }

    /**
     * @return string
     */
    public function getHomegroup() {
        return $this->homegroup;
    }

    /**
     * @param  string $house
     * @return $this
     */
    public function setHouse($house) {
        $this->house = $house;
        return $this;
    }

    /**
     * @return string
     */
    public function getHouse() {
        return $this->house;
    }

    /**
     * @param  string $last_name
     * @return $this
     */
    public function setLastName($last_name) {
        $this->last_name = $last_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName() {
        return $this->last_name;
    }

    /**
     * @param  string $level
     * @return $this
     */
    public function setLevel($level) {
        $this->level = $level;
        return $this;
    }

    /**
     * @return string
     */
    public function getLevel() {
        return $this->level;
    }

    /**
     * @param  string $preferred_name
     * @return $this
     */
    public function setPreferredName($preferred_name) {
        $this->preferred_name = $preferred_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getPreferredName() {
        return $this->preferred_name;
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    /**
     * @param  \Schoolbox\User $user
     * @return $this
     */
    public function setUser($user) {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @param  int $user_id
     * @return $this
     */
    public function setUserId($user_id) {
        $this->user_id = $user_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getUserId() {
        return $this->user_id;
    }
}
