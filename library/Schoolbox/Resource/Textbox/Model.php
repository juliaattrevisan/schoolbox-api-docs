<?php

/**
 * Textbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox;

use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;
use Schoolbox\Resource\Folder\Model as Folder;

class Model implements ISoftDeletable, Serializable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use CreatedBy;
    use UpdatedBy;
    use DeletedBy;

    use SerializeTrait;

    /**
     * Identifier
     *
     * @var int
     */
    private $id;

    /**
     * Category
     *
     * @var Folder
     */
    private $folder;

    /**
     * Textbox heading
     *
     * @var string
     */
    private $heading = '';

    /**
     * Textbox body
     *
     * @var string
     */
    private $body = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setFolder(new Folder());
    }

    /* Getters/Setters
     */

    /**
     * Set body
     *
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set folder or homepage that textbox is located on
     *
     * @param Folder $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Get folder or homepage that textbox is located on
     *
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set heading
     *
     * @param string $heading
     * @return $this
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;
        return $this;
    }

    /**
     * Get heading
     *
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * Set id
     *
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
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
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'folder',
            'heading',
            'body',
            'createdAt',
            'updatedAt',
        ];
    }
}
