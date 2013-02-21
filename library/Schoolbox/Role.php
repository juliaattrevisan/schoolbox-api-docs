<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
namespace Schoolbox;

class Role {
    private $id;
    private $name;
    private $type;          //Type of user group, guest, student, parent, staff
    private $canChangePassword;  //Users can change passwords (bool)

    const GUEST = 'guest';
    const STUDENT = 'student';
    const PARENT = 'parent';
    const STAFF = 'staff';

    /**
     * True if role is student
     *
     * @return bool
     */
    public function isStudent() {
        return $this->type == self::STUDENT;
    }

    /**
     * True if role is staff
     *
     * @return bool
     */
    public function isStaff() {
        return $this->type == self::STAFF;
    }

    /**
     *  True if role is staff
     *
     * @return bool
     */
    public function isParent() {
        return $this->type == self::PARENT;
    }

    public function setCanChangePassword($canChangePassword) {
        $this->canChangePassword = $canChangePassword;
    }

    public function getCanChangePassword() {
        return $this->canChangePassword;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function getType() {
        return $this->type;
    }






}
