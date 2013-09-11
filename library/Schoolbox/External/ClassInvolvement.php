<?php
/**
 * Class involvement object for Schoolbox external connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use Schoolbox\User;

class ClassInvolvement {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var ClassEntry
     */
    protected $class;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param  \Schoolbox\External\ClassEntry $class
     *
     * @return $this
     */
    public function setClass($class) {
        $this->class = $class;
        return $this;
    }

    /**
     * @return \Schoolbox\External\ClassEntry
     */
    public function getClass() {
        return $this->class;
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
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

}
