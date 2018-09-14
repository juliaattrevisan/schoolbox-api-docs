<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Json\Linkable;
use Alaress\Json\SerializeTrait;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Json\Api\AuraLink;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\News\Topic;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Alaress_Date_Format;

class Model implements ISoftDeletable, \Alaress\Json\Serializable, Linkable
{
    // The discrete, atomic states this article may be in.
    // It is quite valid for composite states to be composed of 2+ more atomic states. eg isAccepted = [AWAITING_PUBLICATION|PUBLISHED|EXPIRED]
    // Although explicit usage of the atomics is probably clearer.
    private const STATE_DRAFT                = 1;
    private const STATE_AWAITING_MODERATION  = 2;
    private const STATE_AWAITING_PUBLICATION = 3;
    private const STATE_REJECTED             = 4;
    private const STATE_PUBLISHED            = 5;
    private const STATE_EXPIRED              = 6;

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
     * @var IStorage|null
     */
    private $featureImage;

    /**
     * @var \Alaress_Date_Format
     */
    private $submittedAt;

    /**
     * The person who allowed this article to be published
     *
     * @var User|null
     */
    private $acceptedBy;

    /**
     * The datetime when this article was allowed to be published
     *
     * @var \Alaress_Date_Format
     */
    private $acceptedAt;

    /**
     * The datetime when this article was rejected
     *
     * @var \Alaress_Date_Format
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
     * @var string
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
     * @var Collection|Topic[]
     */
    private $topics;


    /* Conveniences
     */


    /**
     * Returns whether articles is a draft. Stubs will return true.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return self::STATE_DRAFT === $this->getDiscreteState();
    }


    /**
     * @return bool
     */
    public function isAwaitingModeration(): bool
    {
        return self::STATE_AWAITING_MODERATION === $this->getDiscreteState();
    }


    /**
     * This news has been rejected
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return self::STATE_REJECTED === $this->getDiscreteState();
    }


    /**
     * @return bool
     */
    public function isAwaitingPublication(): bool
    {
        return self::STATE_AWAITING_PUBLICATION === $this->getDiscreteState();
    }


    /**
     * Returns whether this article is in the 'published' state
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return self::STATE_PUBLISHED === $this->getDiscreteState();
    }


    /**
     * Returns whether this article is in the 'expired' (aka 'archived') state
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return self::STATE_EXPIRED === $this->getDiscreteState();
    }


    /**
     * This news has been submitted
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isSubmitted(Alaress_Date_Format $now = null): bool
    {
        return $this->isAwaitingModeration() ||
            $this->isAwaitingPublication() ||
            $this->isPublished() ||
            $this->isExpired();
    }


    /**
     * This news has been accepted
     *
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isAccepted(Alaress_Date_Format $now = null): bool
    {
        $acceptedStates = [self::STATE_AWAITING_PUBLICATION, self::STATE_PUBLISHED, self::STATE_EXPIRED];
        return \in_array($this->getDiscreteState($now), $acceptedStates, true);
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
        $acceptedAt  = $this->acceptedAt && !$this->acceptedAt->isNull() ? $this->acceptedAt : null;
        $rejectedAt  = $this->rejectedAt && !$this->rejectedAt->isNull() ? $this->rejectedAt : null;

        /* If the rejected_at timestamp exists, this article has been rejected at some point in its lifetime
         * Rejection details persist until an article's been accepted
         */
        if ($rejectedAt) {

            /* If the submitted_at timestamp is null, the author hasn't resubmitted the article for
             * further moderation
             */
            if (null === $submittedAt) {
                return self::STATE_REJECTED;
            }

            /* If the submitted_at timestamp exists and submitted_at is greater than rejected_at, then
             * the author has resubmitted the article and the state should be Awaiting Moderation again
             */
            if ($submittedAt->gt($rejectedAt)) {
                return self::STATE_AWAITING_MODERATION;
            }
        }

        /* If an article has an accepted_at value then it's either Expired, Published, or Awaiting Publication
         */
        if ($acceptedAt) {
            $now = $now ?? Alaress_Date_Format::now();

            /* We've reached the expiry_at value, so we're expired
             */
            if ($now->gte($this->expiresAt)) {
                return self::STATE_EXPIRED;
            }

            /* We've reached the publish_at value, so we're published
             */
            if ($now->gte($this->publishAt)) {
                return self::STATE_PUBLISHED;
            }

            /* We haven't reached the publish_at value, but we've been accepted
             * We are therefore awaiting publication
             */
            if ($now->lt($this->publishAt)) {
                return self::STATE_AWAITING_PUBLICATION;
            }
        } else {

            /* If an article doesn't have an accepted_at value, then it must be either Draft or Awaiting Moderation
             * (or it's previously been rejected but we covered that case already)
             */
            if (null === $submittedAt) {
                return self::STATE_DRAFT;
            }

            return self::STATE_AWAITING_MODERATION;
        }

        /* Default unknown scenario
         */
        return self::STATE_DRAFT;
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
     * @return null|IStorage
     */
    public function getFeatureImage(): ?IStorage
    {
        return $this->featureImage;
    }


    /**
     * @param null|IStorage $featureImage
     * @return Model
     */
    public function setFeatureImage(?IStorage $featureImage): self
    {
        $this->featureImage = $featureImage;
        return $this;
    }


    /**
     * @return Collection|Topic[]
     */
    public function getTopics()
    {
        return $this->topics;
    }


    /**
     * @param Collection|Topic[] $topics
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
            ]
        ];
    }


    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        return [
            'self'   => new AuraLink('commsNews.getDetail', [ 'articleId' => $this->getId() ]),
            'update' => new AuraLink('commsNews.getUpdate', [ 'articleId' => $this->getId() ]),
        ];
    }

}
