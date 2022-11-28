<?php

namespace Payload\Forum;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

/**
 * This function is to delete forum post and threads.
 *
 */


class DeleteForumThread extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

//    private int $id;
//    private string $deleteForum;

    public function __construct(int $threadID, int $id, string $deleteForum)
    {
        $this->threadID = $threadID;
        $this->deleteForum = $deleteForum;
        $this->id = $id;
        $this->setReferer("/forum/thread/" . $threadID);
    }

    protected function getUrl(): string
    {
        return "/forum/modifyForm.php?" . $this->deleteForum . "=" . $this->id;
    }
}
