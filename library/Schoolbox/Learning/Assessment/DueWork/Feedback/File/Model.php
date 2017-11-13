<?php
/**
 * File
 *
 * A File that was uploaded as part of a Teacher's Feedback
 * for a particular Due Work
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\File;

use \Schoolbox\Resource\Storage\IStorage;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Resource\File\StorableTrait;

class Model implements IStorage
{
    use StorableTrait;
    use CreatedAtDate;

    /**
     * @var int
     */
    protected $id;

    /**
     * The filename which was submitted
     *
     * @var string
     */
    protected $filename;

    /**
     * File constructor.
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
}