<?php

/**
 * CoverPhoto
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

class CoverPhoto
{

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

    /**
     * Created time for the pic
     *
     * @var \Alaress_Date_Format
     */
    private $created_at;

    /**
     * Updated time for the pic
     *
     * @var \Alaress_Date_Format
     */
    private $updated_at;

    /**
     * Deleted time for the pic
     *
     * @var \Alaress_Date_Format|null
     */
    private $deleted_at;

    /* Getters/Setters
     */

    /**
     * Set created at
     *
     * @param \Alaress_Date_Format $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * Get created at
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deleted_at
     */
    public function setDeletedAt($deleted_at)
    {
        $this->deleted_at = $deleted_at;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format|null
     */
    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

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
     * Set updated at
     *
     * @param \Alaress_Date_Format $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    /**
     * Get updated at
     *
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
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
