<?php

namespace Tests\Support\Payload\Forum;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PostTrait;

/**
 * This function is to delete forum post and threads.
 *
 */


class DeleteForumThread extends \Tests\Support\Payload\Request
{
    use PostTrait;
    use FormTrait;

    /**
     * The type of item to delete:
     * * "deletePost" to delete a single post
     * * "deleteThread" to delete an entire thread
     */
    private string $deleteForum;

    /**
     * The ID of the thread or post to delete
     */
    private int $id;

    public function __construct(int $threadID, int $id, string $deleteForum)
    {
        $this->deleteForum = $deleteForum;
        $this->id = $id;
        $this->setReferer("/forum/thread/" . $threadID);
    }

    protected function getUrl(): string
    {
        return "/forum/modifyForm.php?" . $this->deleteForum . "=" . $this->id;
    }
}
