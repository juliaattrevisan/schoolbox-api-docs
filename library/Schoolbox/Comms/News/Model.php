<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\News\Topic;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

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
    private $moderatedBy;

    /**
     * The datetime when this article was allowed to be published
     *
     * @var \Alaress_Date_Format
     */
    private $moderatedAt;

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
    public function getModeratedBy(): ?User
    {
        return $this->moderatedBy;
    }


    /**
     * @param null|User $moderatedBy
     * @return Model
     */
    public function setModeratedBy(?User $moderatedBy): self
    {
        $this->moderatedBy = $moderatedBy;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getModeratedAt(): ?\Alaress_Date_Format
    {
        return $this->moderatedAt;
    }


    /**
     * @param \Alaress_Date_Format $moderatedAt
     * @return $this
     */
    public function setModeratedAt(\Alaress_Date_Format $moderatedAt): self
    {
        $this->moderatedAt = $moderatedAt;
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
     * @param \Alaress_Date_Format $submittedAt
     * @return $this
     */
    public function setSubmittedAt(\Alaress_Date_Format $submittedAt): self
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
}
