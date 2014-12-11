<?php
/**
 * Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;


class Staff extends Base {

    /* Personal
     */

    /**
     * Associated campus
     *
     * @var null
     */
    private $campus = null;

    /**
     * Staff type
     *
     * @var Staff\Type
     */
    private $type = null;

    /* Contact
     */

    /**
     * Phone extension
     *
     * @var string
     */
    private $extension = '';

    /**
     * Work phone
     *
     * @var Phone
     */
    protected $phoneWork = '';
}