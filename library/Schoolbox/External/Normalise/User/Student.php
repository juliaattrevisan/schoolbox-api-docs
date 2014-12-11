<?php
/**
 * Student
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;


class Student extends Base {

    /* Personal
     */

    /**
     * Medical requirements
     *
     * @var string
     */
    protected $medical = '';

    /**
     * Special needs
     *
     * @var string
     */
    protected $specialNeeds = '';

    /**
     * Alert message
     *
     * @var string
     */
    protected $alert = '';

    /**
     * "LockerBook" whatever that is
     *
     * @var string
     */
    protected $locker = '';

    /* Academics
     */

    /**
     * An instance of Year
     *
     * @var Student\Year
     */
    protected $year = null;

    /**
     * An instance of Form
     *
     * @var Student\Form
     */
    protected $form = null;

    /**
     * Tutor
     *
     * @var Staff
     */
    protected $tutor = null;

    /* Parents
     */

    /**
     * A collection of Guardian instances
     *
     * @var \Schoolbox\Collection
     */
    protected $guardians = null;

    /**
     * The parent(s) of this person are separated
     *
     * @var bool
     */
    protected $guardiansSeparated = false;

    /* Contact
     */

    /**
     * House
     *
     * @var House
     */
    protected $house = null;
} 