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

    public function setCategory($folderId)
    {
        $this->setFormParam('category', $folderId);
    }

    public function setDescription(string $description)
    {
        $this->setFormParam('description', $description);
    }

    public function setComment(string $comment)
    {
        $this->setFormParam('comment', $comment);
    }

    public function setVisibility($visibility)
    {
        $this->setFormParam('visible', $visibility);
    }

    public function setHidden($hidden)
    {
        $this->setFormParam('hidden', $hidden);
    }
}
