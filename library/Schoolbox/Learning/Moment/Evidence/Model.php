<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\Discussion\Thread\Model as ThreadModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

/**
 * Class Model
 */
class Model implements ISoftDeletable
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;


    /**
     * @var IStorage|null
     */
    private $storage;


    /**
     * @var string|null
     */
    private $filename;


    /**
     * @var string|null
     */
    private $body;


    /**
     * @var ThreadModel
     */
    private $discussionThread;


    /**
     * @var User
     */
    private $recipient;


    /**
     * @var User
     */
    private $creator;


    /**
     * @return IStorage|null
     */
    public function getStorage(): ?IStorage
    {
        return $this->storage;
    }


    /**
     * @param IStorage|null $storage
     * @return static
     */
    public function setStorage(?IStorage $storage): self
    {
        $this->storage = $storage;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }


    /**
     * @param string|null $filename
     * @return static
     */
    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * @param string|null $body
     * @return static
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return ThreadModel
     */
    public function getDiscussionThread(): ThreadModel
    {
        return $this->discussionThread;
    }


    /**
     * @param ThreadModel $discussionThread
     * @return static
     */
    public function setDiscussionThread(ThreadModel $discussionThread): self
    {
        $this->discussionThread = $discussionThread;
        return $this;
    }


    /**
     * @return User
     */
    public function getRecipient(): User
    {
        return $this->recipient;
    }


    /**
     * @param User $recipient
     * @return static
     */
    public function setRecipient(User $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }


    /**
     * @return User
     */
    public function getCreator(): User
    {
        return $this->creator;
    }


    /**
     * @param User $creator
     * @return static
     */
    public function setCreator(User $creator): self
    {
        $this->creator = $creator;
        return $this;
    }
}
