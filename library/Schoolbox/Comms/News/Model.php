<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Json\Linkable;
use Alaress\Json\SerializeTrait;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Exceptions\InvalidTransitionException;
use Schoolbox\Discussion\Thread;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Json\Api\AuraLink;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use TypeError;

class Model implements ISoftDeletable, \Alaress\Json\Serializable, Linkable, Thread\Context\IThreadLocation
{
    // The discrete, atomic states this article may be in.
    // @see https://www.draw.io/#G1hgNAeuen61KzCzlIa0gEzYK5l0qsYtkw
    public const STATE_DRAFT                = 1;
    public const STATE_AWAITING_MODERATION  = 2;
    public const STATE_AWAITING_PUBLICATION = 4;
    public const STATE_REJECTED             = 8;
    public const STATE_PUBLISHED            = 16;
    public const STATE_EXPIRED              = 32;

    public const STATE_NONE = 0;
    public const STATE_ALL  =
        self::STATE_DRAFT | self::STATE_AWAITING_MODERATION | self::STATE_AWAITING_PUBLICATION |
        self::STATE_REJECTED | self::STATE_PUBLISHED | self::STATE_EXPIRED;

    // Composite states
    public const STATE_PUBLIC    = self::STATE_PUBLISHED | self::STATE_EXPIRED;
    public const STATE_APPROVED  = self::STATE_AWAITING_PUBLICATION | self::STATE_PUBLISHED | self::STATE_EXPIRED;
    public const STATE_SUBMITTED = self::STATE_AWAITING_MODERATION | self::STATE_REJECTED | self::STATE_AWAITING_PUBLICATION | self::STATE_PUBLISHED | self::STATE_EXPIRED;

    // Authoritative declaration of *possible* transitions.
    // *Permitted* (ie for some user) transitions are always a subset of these.
    // The latter concept is the domain of route auth rules.
    // Action=Submit
    private const VALID_STATES_TO_SUBMIT    = self::STATE_DRAFT | self::STATE_REJECTED;
    // Action=UnSubmit
    private const VALID_STATES_TO_UN_SUBMIT = self::STATE_SUBMITTED;
    // Action=Approve
    private const VALID_STATES_TO_APPROVE   = self::STATE_DRAFT | self::STATE_AWAITING_MODERATION | self::STATE_REJECTED;
    // Action=Reject
    private const VALID_STATES_TO_REJECT    = self::STATE_AWAITING_MODERATION | self::STATE_AWAITING_PUBLICATION | self::STATE_PUBLISHED | self::STATE_EXPIRED;
    // Action=Publish
    private const VALID_STATES_TO_PUBLISH   = self::STATE_AWAITING_PUBLICATION;
    // Action=Archive
    private const VALID_STATES_TO_ARCHIVE   = self::STATE_AWAITING_PUBLICATION | self::STATE_PUBLISHED;

    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use SerializeTrait;


    /**
     * Title of the article
     *
     * @var string
     */
    private $title;

    /**
     * Main body of the article
     *
     * @var string
     */
    private $body;

    /**
     * A short version of the $body that appears in places like email digest etc.
     *
     * @var string
     */
    private $blurb;

    /**
     * The person who authored this article
     *
     * @var User
     */
    private $author;

    /**
     * @var bool
     */
    private $sticky;


    /**
     * @var Storage\Model|null
     */
    private $featureImage;

    /**
     * @var Thread\Model|null
     */
    private $discussionThread;

    /**
     * @var Alaress_Date_Format|null
     */
    private $submittedAt;

    /**
     * @var bool
     */
    private $commentsAllowed;

    /**
     * The person who allowed this article to be published
     *
     * @var User|null
     */
    private $acceptedBy;

    /**
     * The datetime when this article was allowed to be published
     *
     * @var Alaress_Date_Format|null
     */
    private $acceptedAt;

    /**
     * The datetime when this article was rejected
     *
     * @var Alaress_Date_Format|null
     */
    private $rejectedAt;

    /**
     * The person who rejected this article
     *
     * @var User|null
     */
    private $rejectedBy;

    /**
     * The reason why this article was rejected
     *
     * @var string|null
     */
    private $rejectedReason;

    /**
     * The article becomes available to the audience at this time
     *
     * @var \Alaress_Date_Format
     */
    private $publishAt;

    /**
     * The article becomes archived at this time
     *
     * @var \Alaress_Date_Format
     */
    private $expiresAt;


    /**
     * A set of News\Topic instances - there used to be only one, but now there are zero or many
     *
     * @var Collection|Topic\Model[]
     */
    private $topics;

    /** @var GenericCollection|Audience\Model[] */
    private $audience;

    /**
     * @var Metadata
     */
    private $metadata;

    public function __construct()
    {
        $this->metadata = new Metadata();
    }


    // Discrete states


    /**
     * Returns whether articles is a draft. Stubs will return true.
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isDraft(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_DRAFT === $this->getDiscreteState($now);
    }


    /**
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isAwaitingModeration(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_AWAITING_MODERATION === $this->getDiscreteState($now);
    }


    /**
     * This news has been rejected
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isRejected(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_REJECTED === $this->getDiscreteState($now);
    }


    /**
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isAwaitingPublication(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_AWAITING_PUBLICATION === $this->getDiscreteState($now);
    }


    /**
     * Returns whether this article is in the 'published' state
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isPublished(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_PUBLISHED === $this->getDiscreteState($now);
    }


    /**
     * Returns whether this article is in the 'expired' (aka 'archived') state
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isExpired(Alaress_Date_Format $now = null): bool
    {
        return self::STATE_EXPIRED === $this->getDiscreteState($now);
    }

    // Composite states.

    /**
     * Returns whether the article has been approved for publishing.
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isApproved(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::STATE_APPROVED;
    }


    /**
     * Returns whether this article has been submitted
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isSubmitted(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::STATE_SUBMITTED;
    }


    /**
     * Returns whether this article is public (either published or archived)
     *
     * TODO consider whether "public" is the right term
     * ("Public" implies it's visible to all, whilst here it means it's only
     *  visible to its prescribed audience.)
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isPublic(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::STATE_PUBLIC;
    }


    /**
     * Returns the discrete atomic state of this article.
     *
     * Stub articles will return 'DRAFT'.
     *
     * Does not cater for deleted articles, that is a different dimension.
     *
     * @param Alaress_Date_Format|null $now
     * @return int
     */
    private function getDiscreteState(Alaress_Date_Format $now = null): int
    {
        // Needed to compensate for AlaressNullDate object/null duality
        $submittedAt = $this->submittedAt && !$this->submittedAt->isNull() ? $this->submittedAt : null;
        $approvedAt  = $this->acceptedAt && !$this->acceptedAt->isNull() ? $this->acceptedAt : null;
        $rejectedAt  = $this->rejectedAt && !$this->rejectedAt->isNull() ? $this->rejectedAt : null;

        if (!$submittedAt) {
            return self::STATE_DRAFT;
        }

        // Populated when rejected and exists until either (re-)submitted or approved.
        if ($rejectedAt) {
            return self::STATE_REJECTED;
        }

        if (!$approvedAt) {
            return self::STATE_AWAITING_MODERATION;
        }

        $now = $now ?? Alaress_Date_Format::now();

        if ($now->lt($this->publishAt)) {
            return self::STATE_AWAITING_PUBLICATION;
        }

        if ($now->lt($this->expiresAt)) {
            return self::STATE_PUBLISHED;
        }

        return self::STATE_EXPIRED;
    }


    /**
     * This article has Sharing enabled
     *
     * TODO: This is currently a stub until Shares gets built; defaults to false for now
     *
     * @return bool
     */
    public function isShareable(): bool
    {
        return false;
    }


    /**
     * @param Alaress_Date_Format|null $now
     * @return string
     */
    public function getStatus(Alaress_Date_Format $now = null): string
    {
        $map = [
            self::STATE_DRAFT                => 'Drafted',
            self::STATE_AWAITING_MODERATION  => 'Awaiting Moderation',
            self::STATE_AWAITING_PUBLICATION => 'Awaiting Publication',
            self::STATE_REJECTED             => 'Rejected',
            self::STATE_PUBLISHED            => 'Published',
            self::STATE_EXPIRED              => 'Archived',
        ];

        return $map[$this->getDiscreteState($now)];
    }


    /**
     * Returns whether this article can be submitted
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function canBeSubmitted(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::VALID_STATES_TO_SUBMIT;
    }


    /**
     * Returns whether this article can be un-submitted
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function canBeUnSubmitted(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::VALID_STATES_TO_UN_SUBMIT;
    }


    /**
     * Returns whether this article can be approved
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function canBeApproved(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::VALID_STATES_TO_APPROVE;
    }


    /**
     * Returns whether this article can be rejected
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function canBeRejected(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::VALID_STATES_TO_REJECT;
    }


    /**
     * Returns whether this article can be archived
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function canBeArchived(Alaress_Date_Format $now = null): bool
    {
        return $this->getDiscreteState($now) & self::VALID_STATES_TO_ARCHIVE;
    }


    /**
     * Transitions an article to the 'Awaiting Moderation' state
     *
     * @param Alaress_Date_Format|null $now
     * @throws InvalidTransitionException If transition is invalid for some reason
     */
    public function submit(Alaress_Date_Format $now = null): void
    {
        $now          = $now ?? Alaress_Date_Format::now();
        $currentState = $this->getDiscreteState($now);

        // Already submitted. No risk to caller of data loss
        if (self::STATE_AWAITING_MODERATION === $currentState) {
            throw new InvalidTransitionException($currentState, "Article was already submitted at {$this->submittedAt->getDatabase()}");
        }

        // Invalid operation
        if (!($currentState & self::VALID_STATES_TO_SUBMIT)) {
            throw new InvalidTransitionException($currentState, "Only draft and rejected articles may be submitted. Found '{$this->getStatus($now)}'");
        }

        $this->submittedAt = $now;
        $this->rejectedAt  = null;
    }


    /**
     * Transitions an article to the 'Draft' state
     *
     * @throws InvalidTransitionException If transition is invalid for some reason
     */
    public function unSubmit(): void
    {
        $currentState = $this->getDiscreteState();

        // Already submitted. No risk to caller of data loss
        if (self::STATE_DRAFT === $currentState) {
            throw new InvalidTransitionException($currentState, "Article is already a draft");
        }

        if (!($currentState & self::VALID_STATES_TO_UN_SUBMIT)) {
            throw new InvalidTransitionException($currentState, "Cannot un-submit article. Found '{$this->getStatus()}'");
        }

        // Put back to draft state
        $this->submittedAt = null;

        // Note that we don't clear the rejection details so that author can still see why their article
        // was rejected.

        // Clear approval details - so it will need to be re-approved if re-submitted
        $this->acceptedAt = null;
        $this->acceptedBy = null;
    }


    /**
     * Transitions an article to the 'Approved' state
     *
     * @param User                     $effector
     * @param Alaress_Date_Format|null $now
     * @throws InvalidTransitionException
     */
    public function approve(User $effector, Alaress_Date_Format $now = null): void
    {
        $now          = $now ?? Alaress_Date_Format::now();
        $currentState = $this->getDiscreteState($now);

        // Already approved.
        if ($this->isApproved($now)) {
            throw new InvalidTransitionException($currentState, "Article was already approved by user {$this->acceptedBy->getId()} at {$this->acceptedAt->getDatabase()}");
        }

        // Ensure it is able to be approved
        if (!($currentState & self::VALID_STATES_TO_APPROVE)) {
            throw new InvalidTransitionException($currentState, "Article state does not permit approval. Found '{$this->getStatus($now)}'");
        }

        // Ensure it is submitted
        if (!$this->isSubmitted($now)) {
            $this->submit($now);
        }

        // Treat as never being rejected
        $this->rejectedAt     = null;
        $this->rejectedBy     = null;
        $this->rejectedReason = null;

        // Set acceptance details
        $this->acceptedAt = $now;
        $this->acceptedBy = $effector;
    }


    /**
     * Transitions an article to the 'Rejected' state
     *
     * @param User                     $effector
     * @param string|null              $reason
     * @param Alaress_Date_Format|null $now
     * @throws InvalidTransitionException
     */
    public function reject(User $effector, string $reason = null, Alaress_Date_Format $now = null): void
    {
        $now          = $now ?? Alaress_Date_Format::now();
        $currentState = $this->getDiscreteState($now);

        // Already rejected.
        if (self::STATE_REJECTED === $currentState) {
            throw new InvalidTransitionException($currentState, "Article was already rejected by user {$this->rejectedBy->getId()} at {$this->rejectedAt->getDatabase()}");
        }

        // Invalid operation
        if (!($currentState & self::VALID_STATES_TO_REJECT)) {
            throw new InvalidTransitionException($currentState, "Article state does not permit rejection. Found '{$this->getStatus($now)}'");
        }

        // Treat article as never being approved.
        $this->acceptedAt = null;
        $this->acceptedBy = null;

        // Set rejection details
        $this->rejectedBy     = $effector;
        $this->rejectedAt     = $now;
        $this->rejectedReason = $reason;
    }


    /**
     * Transitions an article to the 'published' state
     *
     * Normally this would immediately publish any article awaiting publication
     *
     * @param Alaress_Date_Format|null $publishAt
     * @param Alaress_Date_Format|null $now
     * @throws InvalidTransitionException
     */
    public function publish(Alaress_Date_Format $publishAt = null, Alaress_Date_Format $now = null)
    {
        $now          = $now ?? Alaress_Date_Format::now();
        $currentState = $this->getDiscreteState($now);

        // Already published?
        if (self::STATE_PUBLISHED === $currentState) {
            throw new InvalidTransitionException($currentState, "Article has been published as of {$this->publishAt->getDatabase()}");
        }

        // Ensure transition is valid
        if (!($currentState & self::VALID_STATES_TO_PUBLISH)) {
            throw new InvalidTransitionException($currentState, "Article state does not permit publish. Found '{$this->getStatus($now)}'");
        }

        // Default to publish immediately
        $publishAt = $publishAt ?? Alaress_Date_Format::now();

        // Ensure the expiry date is always never precedes the publish date
        if ($publishAt->gt($this->expiresAt)) {
            $this->expiresAt = $publishAt;
        }

        $this->publishAt = $publishAt ?? Alaress_Date_Format::now();
    }


    /**
     * Transitions an article to the 'expired' state
     *
     * @param Alaress_Date_Format|null $expireAt
     * @param Alaress_Date_Format|null $now
     * @throws InvalidTransitionException
     */
    public function archive(Alaress_Date_Format $expireAt = null, Alaress_Date_Format $now = null)
    {
        $now          = $now ?? Alaress_Date_Format::now();
        $currentState = $this->getDiscreteState($now);

        // Already archived?
        if (self::STATE_EXPIRED === $currentState) {
            throw new InvalidTransitionException($currentState, "Article has been expired as of {$this->expiresAt->getDatabase()}");
        }

        // Ensure transition is valid
        if (!($currentState & self::VALID_STATES_TO_ARCHIVE)) {
            throw new InvalidTransitionException($currentState, "Article state does not permit archive. Found '{$this->getStatus($now)}'");
        }

        // Default to expiring now
        $expireAt = $expireAt ?? Alaress_Date_Format::now();

        // Ensure the publish date does not become after the expire date
        if ($this->publishAt->gt($expireAt)) {
            $this->publishAt = $expireAt;
        }

        $this->expiresAt = $expireAt;
    }


    /**
     * @param Alaress_Date_Format|null $now
     * @return Alaress_Date_Format|null
     */
    public function getStatusDate(Alaress_Date_Format $now = null): ?Alaress_Date_Format
    {
        switch ($this->getDiscreteState($now)) {
            case self::STATE_PUBLISHED:
                return $this->publishAt;

            case self::STATE_REJECTED:
                return $this->rejectedAt;

            case self::STATE_EXPIRED:
                return $this->expiresAt;

            case self::STATE_AWAITING_PUBLICATION:
                return $this->acceptedAt;

            case self::STATE_DRAFT:
            case self::STATE_AWAITING_MODERATION:
            default:
                return $this->createdAt;
        }
    }

    /* Getters/Setters
     */

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * @param string $body
     * @return $this
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getBlurb(): ?string
    {
        return $this->blurb;
    }


    /**
     * @param string $blurb
     * @return $this
     */
    public function setBlurb(string $blurb): self
    {
        $this->blurb = $blurb;
        return $this;
    }


    /**
     * @return null|User
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }


    /**
     * @param User $author
     * @return $this
     */
    public function setAuthor(User $author): self
    {
        $this->author = $author;
        return $this;
    }


    /**
     * @return bool
     */
    public function getSticky(): ?bool
    {
        return $this->sticky;
    }


    /**
     * @param null|bool $sticky
     * @return $this
     */
    public function setSticky(?bool $sticky): self
    {
        $this->sticky = $sticky;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getPublishAt(): ?\Alaress_Date_Format
    {
        return $this->publishAt;
    }


    /**
     * @param \Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt(\Alaress_Date_Format $publishAt): self
    {
        $this->publishAt = $publishAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getExpiresAt(): ?\Alaress_Date_Format
    {
        return $this->expiresAt;
    }


    /**
     * @param \Alaress_Date_Format $expiresAt
     * @return $this;
     */
    public function setExpiresAt(\Alaress_Date_Format $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }


    /**
     * @return null|bool
     */
    public function getCommentsAllowed(): ?bool
    {
        return $this->commentsAllowed;
    }


    /**
     * @param bool $commentsAllowed
     * @return static
     */
    public function setCommentsAllowed(bool $commentsAllowed): self
    {
        $this->commentsAllowed = $commentsAllowed;
        return $this;
    }


    /**
     * @return null|User
     */
    public function getAcceptedBy(): ?User
    {
        return $this->acceptedBy;
    }


    /**
     * @param null|User $acceptedBy
     * @return Model
     */
    public function setAcceptedBy(?User $acceptedBy): self
    {
        $this->acceptedBy = $acceptedBy;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getAcceptedAt(): ?\Alaress_Date_Format
    {
        return $this->acceptedAt;
    }


    /**
     * @param null|\Alaress_Date_Format $acceptedAt
     * @return $this
     */
    public function setAcceptedAt(?\Alaress_Date_Format $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;
        return $this;
    }


    /**
     * @return null|Storage\Model
     */
    public function getFeatureImage(): ?Storage\Model
    {
        return $this->featureImage;
    }


    /**
     * @param null|Storage\Model $featureImage
     * @return Model
     */
    public function setFeatureImage(?Storage\Model $featureImage): self
    {
        $this->featureImage = $featureImage;
        return $this;
    }


    /**
     * @return Thread\Model|null
     */
    public function getDiscussionThread(): ?Thread\Model
    {
        return $this->discussionThread;
    }


    /**
     * @param Thread\Model|null $discussionThread
     * @return static
     */
    public function setDiscussionThread(?Thread\Model $discussionThread): self
    {
        $this->discussionThread = $discussionThread;
        return $this;
    }


    /**
     * @return Collection|Topic\Model[]
     */
    public function getTopics()
    {
        return $this->topics;
    }


    /**
     * @param Collection|Topic\Model[] $topics
     * @return $this
     */
    public function setTopics(Collection $topics): self
    {
        $this->topics = $topics;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getSubmittedAt(): ?\Alaress_Date_Format
    {
        return $this->submittedAt;
    }


    /**
     * @param null|\Alaress_Date_Format $submittedAt
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
    public function getRejectedAt(): ?\Alaress_Date_Format
    {
        return $this->rejectedAt;
    }


    /**
     * @param \Alaress_Date_Format $rejectedAt
     * @return $this
     */
    public function setRejectedAt(\Alaress_Date_Format $rejectedAt): self
    {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }


    /**
     * @return null|User
     */
    public function getRejectedBy(): ?User
    {
        return $this->rejectedBy;
    }


    /**
     * @param null|User $rejectedBy
     * @return $this
     */
    public function setRejectedBy(?User $rejectedBy): self
    {
        $this->rejectedBy = $rejectedBy;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getRejectedReason(): ?string
    {
        return $this->rejectedReason;
    }


    /**
     * @param null|string $rejectedReason
     * @return $this
     */
    public function setRejectedReason(?string $rejectedReason): self
    {
        $this->rejectedReason = $rejectedReason;
        return $this;
    }


    /**
     * @return Audience\Model[]|GenericCollection
     */
    public function getAudience(): ?GenericCollection
    {
        return $this->audience;
    }


    /**
     * @param GenericCollection|Audience\Model[] $audience
     * @throws TypeError If the new collection contains items of an incompatible type
     * @return $this
     */
    public function setAudience(GenericCollection $audience): self
    {
        $audience->assertCollectionOf(Audience\Model::class);
        $this->audience = $audience;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'title',
            'blurb',
            'body',
            'author',
            'featureImage',
            'publishAt',
            'topics' => [
                'name',
                'slug',
            ],
            'status',
            'statusDate'
        ];
    }


    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        // Set auth to FALSE on 'commsNews.getDetail' as unlikely we'll have fetched this instance if the user is unable to see it
        return [
            'self'   => new AuraLink('commsNews.getDetail', [ 'article' => $this, 'articleId' => $this->getId() ], false),
            'update' => new AuraLink('commsNews.getUpdate', [ 'article' => $this, 'articleId' => $this->getId() ]),
            'saveForLater' => new AuraLink('commsNewsSaveForLater.postAdd', [ 'article' => $this, 'articleId' => $this->getId() ]),
            'unsaveForLater' => new AuraLink('commsNewsSaveForLater.postRemove', [ 'article' => $this, 'articleId' => $this->getId() ])
        ];
    }


    /**
     * @inheritDoc
     */
    public function getCommentNotifier(): string
    {
        return Discussion\Comment\Notifier::class;
    }

    /**
     * @param Metadata $metadata
     *
     * @return $this
     */
    public function setMetadata(Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return Metadata
     */
    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }
}
