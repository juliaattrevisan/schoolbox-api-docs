<?php

namespace Tests\Support\Payload\Forum;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PostTrait;

class CreateForumThread extends \Tests\Support\Payload\Request
{
    use PostTrait;
    use FormTrait;

    /**
     * This function is to create, modify and reply forum threads.
     * To send a request to create a thread submit value should be Post.
     * To send a request to modify a thread submit value should be Modify.
     * To send a request to reply a thread submit value should be Post.
     * @param int    $categoryID
     * @param string $title
     * @param string $submitValue
     */
    public function __construct(int $categoryID, string $title, string $submitValue)
    {
        $this->setReferer("/forum/modify/$categoryID");
        $this->setFormParam('categoryID', $categoryID);
        $this->setFormParam('threadID', '');
        $this->setFormParam('postID', '');
        $this->setFormParam('quoteID', '');
        $this->setFormParam('title', $title);
        $this->setFormParam('body', '');
        $this->setFormParam('btnSubmit', $submitValue);
    }

    protected function getUrl(): string
    {
        return "/forum/modifyForm.php";
    }
}
