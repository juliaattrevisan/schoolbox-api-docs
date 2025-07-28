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
     * The types of evidence
     */
    public const MOMENT_TYPE_TEXT   = 'text';
    public const MOMENT_TYPE_UPLOAD = 'upload';
    public const MOMENT_TYPE_PHOTO  = 'photo';
    public const MOMENT_TYPE_AUDIO  = 'audio';
    public const MOMENT_TYPE_VIDEO  = 'video';


    /**
     * Find the current state of a learning moment
     * "draft" is inferred if none of the others are applicable.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';


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
     * The type of evidence: photo/text/upload/video/audio
     */
    private string $type;

    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $curriculumNodes = null;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $tags = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $submittedAt = null;


    /**
     * @var User|null
     */
    private ?User $submittedBy = null;


    /**
     * @var \Alaress_Date_Format|null
     */

    private ?\Alaress_Date_Format $publishedAt = null;


    /**
     * @var User|null
     */
    private ?User $publishedBy = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $rejectedAt = null;


    /**
     * @var User|null
     */
    private ?User $rejectedBy = null;


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
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;
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
     * Returns just the Curriculum nodes that do not have an empty code.
     * usually these are parent objects, or titles
     *
     * @return GenericCollection|null
     */
    public function getViewableCurriculumNodes(): ?GenericCollection
    {
        return $this->curriculumNodes?->filter(
            function ($node) {
                if ($node->getCode() !== '') {
                    return true;
                } else {
                    return false;
                }
            }
        );
    }


    /**
     * Set a learning moment to Draft status,
     * ready for the next state transition to Submitted
     *
     * @return $this
     */
    public function draft(): self
    {
        // Treat as never being submitted
        $this->submittedAt = null;
        $this->submittedBy = null;

        // Treat as never being published
        $this->publishedAt = null;
        $this->publishedBy = null;

        // Treat as never being rejected
        $this->rejectedAt = null;
        $this->rejectedBy = null;

        return $this;
    }


    /**
     * Set a learning moment to Submitted status, along with its effector,
     * ready for the next state transition to Published or Rejected
     *
     * @param User $effector
     * @param \Alaress_Date_Format|null $now
     * @return $this
     */
    public function submit(User $effector, \Alaress_Date_Format $now = null): self
    {
        $this->setSubmittedBy($effector);

        /* Use the current datetime if one wasn't explicitly provided
         */
        if ($now) {
            $this->setSubmittedAt($now);
        } else {
            $this->setSubmittedAt(\Alaress_Date_Format::now());
        }

        // Treat as never being published
        $this->publishedAt = null;
        $this->publishedBy = null;

        // Treat as never being rejected
        $this->rejectedAt = null;
        $this->rejectedBy = null;

        return $this;
    }


    /**
     * Set a learning moment to Published status, along with its effector
     *
     * @param User $effector
     * @param \Alaress_Date_Format|null $now
     * @return $this
     */
    public function publish(User $effector, \Alaress_Date_Format $now = null): self
    {
        $this->setPublishedBy($effector);

        /* Use the current datetime if one wasn't explicitly provided
         */
        if ($now) {
            $this->setPublishedAt($now);
        } else {
            $this->setPublishedAt(\Alaress_Date_Format::now());
        }

        /* Clear submission date once published
         */
        $this->submittedAt = null;
        $this->submittedBy = null;

        // Treat as never being rejected
        $this->rejectedAt = null;
        $this->rejectedBy = null;

        return $this;
    }


    /**
     * Set a learning moment to Rejected status, along with its effector
     *
     * @param User $effector
     * @param \Alaress_Date_Format|null $now
     * @return $this
     */
    public function reject(User $effector, \Alaress_Date_Format $now = null): self
    {
        $this->setRejectedBy($effector);

        /* Use the current datetime if one wasn't explicitly provided
         */
        if ($now) {
            $this->setRejectedAt($now);
        } else {
            $this->setRejectedAt(\Alaress_Date_Format::now());
        }

        // Treat as never being submitted (back to author to rework it)
        $this->submittedAt = null;
        $this->submittedBy = null;

        // Treat as never being published
        $this->publishedAt = null;
        $this->publishedBy = null;

        return $this;
    }

    /* Convenient bools
     */

    /**
     * This moment is submitted;
     * it isn't published, nor rejected
     *
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return $this->getSubmittedAt() !== null &&
            $this->getSubmittedBy() !== null &&
            $this->getPublishedAt() === null &&
            $this->getPublishedBy() === null &&
            $this->getRejectedAt() === null &&
            $this->getRejectedBy() === null;
    }


    /**
     * This moment is published;
     * it isn't rejected, but we know when it was submitted
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->getSubmittedAt() === null &&
            $this->getSubmittedBy() === null &&
            $this->getPublishedAt() !== null &&
            $this->getPublishedBy() !== null &&
            $this->getRejectedAt() === null &&
            $this->getRejectedBy() === null;
    }


    /**
     * Return the current state of a learning moment, defaulting to Draft
     * if it isn't submitted/published/rejected
     *
     * @return string
     */
    public function getCurrentState(): string
    {
        if ($this->isPublished()) {
            return self::STATUS_PUBLISHED;
        }

        if ($this->isSubmitted()) {
            return self::STATUS_SUBMITTED;
        }

        if ($this->isRejected()) {
            return self::STATUS_REJECTED;
        }

        return self::STATUS_DRAFT;
    }


    /**
     * This moment is rejected;
     * it's not published and we treat this like it was never submitted,
     * forcing the user to go back and revise their content
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->getSubmittedAt() === null &&
            $this->getSubmittedBy() === null &&
            $this->getPublishedAt() === null &&
            $this->getPublishedBy() === null &&
            $this->getRejectedAt() !== null &&
            $this->getRejectedBy() !== null;
    }


    /* Getters/Setters
     */

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
        return Discussion\Comment\Notifier::class;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getSubmittedAt(): ?\Alaress_Date_Format
    {
        return $this->submittedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $submittedAt
     * @return $this
     */
    public function setSubmittedAt(?\Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getPublishedAt(): ?\Alaress_Date_Format
    {
        return $this->publishedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $publishedAt
     * @return $this
     */
    public function setPublishedAt(?\Alaress_Date_Format $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getRejectedAt(): ?\Alaress_Date_Format
    {
        return $this->rejectedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $rejectedAt
     * @return $this
     */
    public function setRejectedAt(?\Alaress_Date_Format $rejectedAt): self
    {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }


    /**
     * @param User|null $submittedBy
     * @return $this
     */
    public function setSubmittedBy(?User $submittedBy): self
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getPublishedBy(): ?User
    {
        return $this->publishedBy;
    }


    /**
     * @param User|null $publishedBy
     * @return $this
     */
    public function setPublishedBy(?User $publishedBy): self
    {
        $this->publishedBy = $publishedBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getRejectedBy(): ?User
    {
        return $this->rejectedBy;
    }


    /**
     * @param User|null $rejectedBy
     * @return $this
     */
    public function setRejectedBy(?User $rejectedBy): self
    {
        $this->rejectedBy = $rejectedBy;
        return $this;
    }
}
