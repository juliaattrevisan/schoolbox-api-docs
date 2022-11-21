<?php

/**
 * EventActivityLog
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ActivityLog;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;

class Log
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Log id
     *
     * @var int
     */
    protected $id = '';

    /**
     * An instance of \Schoolbox\User
     *
     * @var User
     */
    protected $actioner;

    /**
     * Metadata record
     *
     * @var int
     */
    protected $metadataId = -1;

    /**
     * Partial message
     *
     * @var string
     */
    protected $text = '';

    /**
     * The message to display
     *
     * @var string
     */
    protected $message = '';

    /**
     * The final notification string
     *
     * @var string
     */
    protected $result = '';

    /**
     * Hook method
     */
    protected function setup()
    {
    }

    /**
     * Notify
     *
     * @return mixed|void
     */
    public function notify()
    {
        Mapper::getInstance()->save($this);
    }

    /* Getters/Setters
     */

    /**
     * Set actioner
     *
     * @param User $actioner
     */
    public function setActioner($actioner)
    {
        $this->actioner = $actioner;
    }

    /**
     * Get actioner
     *
     * @return User
     */
    public function getActioner()
    {
        return $this->actioner;
    }

    /**
     * Set id
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set message
     *
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set text
     *
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set metadata id
     *
     * @param int $metadataId
     */
    public function setMetadataId($metadataId)
    {
        $this->metadataId = $metadataId;
    }

    /**
     * Get metadata id
     *
     * @return int
     */
    public function getMetadataId()
    {
        return $this->metadataId;
    }
}
