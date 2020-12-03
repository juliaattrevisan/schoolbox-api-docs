<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\Discussion\Thread\Model as ThreadModel;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

/**
 * Class Model
 */
class Model implements ISoftDeletable, IThreadLocation
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;


    /**
     * The file content (if any) that was uploaded to the learning moment.
     *
     * @var IStorage|null
     */
    private ?IStorage $storage = null;


    /**
     * The filename (if any) that was uploaded to the learning moment.
     *
     * @var string|null
     */
    private ?string $filename = null;


    /**
     * The text box (if any) that was uploaded to the learning moment.
     *
     * @var string|null
     */
    private ?string $body = null;


    /**
     * The discussion thread for the learning moment, if it has one.
     *
     * @var ThreadModel|null
     */
    private ?ThreadModel $discussionThread = null;


    /**
     * The recipient of the learning moment.
     *
     * (the Evidence being obtained is about the Recipient)
     *
     * @var User
     */
    private User $recipient;


    /**
     * The user which created the learning moment.
     *
     * @var User
     */
    private User $creator;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $curriculumNodes = null;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $tags = null;

    
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
     * @return ThreadModel|null
     */
    public function getDiscussionThread(): ?ThreadModel
    {
        return $this->discussionThread;
    }


    /**
     * @param ThreadModel|null $discussionThread
     * @return static
     */
    public function setDiscussionThread(?ThreadModel $discussionThread): self
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


    /**
     * @return GenericCollection|null
     */
    public function getCurriculumNodes(): ?GenericCollection
    {
        return $this->curriculumNodes;
    }


    /**
     * @param GenericCollection $curriculumNodes
     * @return static
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes): self
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getTags(): ?GenericCollection
    {
        return $this->tags;
    }


    /**
     * @param GenericCollection $tags
     * @return static
     */
    public function setTags(GenericCollection $tags): self
    {
        $this->tags = $tags;
        return $this;
    }


    /**
     * @inheritdoc
     */
    public function getCommentNotifier(): ?string
    {
        return null;
    }
}
