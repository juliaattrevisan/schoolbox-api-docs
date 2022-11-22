<?php

/**
 * CoverPhoto
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class CoverPhoto
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The primary key
     *
     * @var int
     */
    private $id;

    /**
     * The owner of the page
     *
     * @var int
     */
    private $uid;

    /**
     * The blackhole storage hash
     *
     * @var string
     */
    private $imagehash;

    /* Getters/Setters
     */

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set uid
     *
     * @param int $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * Get uid
     *
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * Set image hash
     *
     * @param string $imagehash
     */
    public function setImagehash($imagehash)
    {
        $this->imagehash = $imagehash;
    }

    /**
     * Get image hash
     *
     * @return string
     */
    public function getImagehash()
    {
        return $this->imagehash;
    }
}
