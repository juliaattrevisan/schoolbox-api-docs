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
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

class Model
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
     * @var IStorage
     */
    private $featureImage;

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
     * @var Collection
     */
    private $tags;

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
     * @return IStorage
     */
    public function getFeatureImage(): ?IStorage
    {
        return $this->featureImage;
    }


    /**
     * @param IStorage $featureImage
     * @return $this
     */
    public function setFeatureImage(IStorage $featureImage): self
    {
        $this->featureImage = $featureImage;
        return $this;
    }


    /**
     * @return Collection
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }


    /**
     * @param Collection $tags
     * @return $this
     */
    public function setTags(Collection $tags): self
    {
        $this->tags = $tags;
        return $this;
    }
}