<?php
/**
 * User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;


abstract class Base {

    /* Personal
     */

    /**
     * A unique identifier
     *
     * @var string
     */
    protected $id = '';

    /**
     * Title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Firstname
     *
     * @var string
     */
    protected $givenName = '';

    /**
     * Preferred firstname
     *
     * @var string
     */
    protected $preferredName = '';

    /**
     * Lastname
     *
     * @var string
     */
    protected $lastname = '';

    /**
     * Gender
     *
     * @var string
     */
    protected $gender = '';

    /**
     * Date of birth
     *
     * @var \Alaress_Date_Format
     */
    protected $dob = null;

    /**
     * OS filesystem path to the user's portrait
     *
     * @var string
     */
    protected $portrait = '';

    /* Network
     */

    /**
     * User account info
     *
     * @var Common\Account
     */
    protected $account = null;

    /* Contact
     */

    /**
     * An address
     *
     * @var Address
     */
    protected $postalAddress = null;

    /**
     * An address
     *
     * @var Common\Address
     */
    protected $homeAddress = null;

    /**
     * Email address
     *
     * @var string
     */
    protected $email = '';

    /**
     * Home phone
     *
     * @var Common\Phone
     */
    protected $phoneHome = '';

    /**
     * Mobile phone
     *
     * @var Common\Phone
     */
    protected $phoneMobile = '';

    /**
     * A collection of Common\Campus instances
     *
     * @var \Schoolbox\Collection
     */
    protected $campus = null;

    /* Publicity
     */

    /**
     * Whether or not to display this person in reports, listings
     * Synonymous with DirectoryIncludeFlag
     *
     * @var bool
     */
    protected $publiclyVisible = true;
}