<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Payload\Resource\File;

use Payload;

use Payload\PostTrait;

abstract class Upload extends Payload\Request
{
    use PostTrait;

    /**
     * Set folder id that the upload will go under.
     *
     * @param $folderId
     */
    public function setCategory($folderId)
    {
        $this->setFormParam('category', $folderId);
    }

    /**
     * Sets description of the file that was uploaded (used for the name of the file for some reason)
     *
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->setFormParam('description', $description);
    }

    /** Sets a comment on the file (actually the description of the file)
     *
     * @param string $comment
     */
    public function setComment(string $comment)
    {
        $this->setFormParam('comment', $comment);
    }

    /**
     * Sets if the file is visible
     *
     * @param $visibility
     */
    public function setVisibility($visibility)
    {
        $this->setFormParam('visible', $visibility);
    }

    /**
     * Sets the file hidden value. For some reason the homepage sets a hidden of 0 as opposed to using the above.
     *
     * @param $hidden
     */
    public function setHidden($hidden)
    {
        $this->setFormParam('hidden', $hidden);
    }
}
