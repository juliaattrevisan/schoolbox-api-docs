<?php

/**
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Complex;

use Schoolbox\Comms\News;

/**
 * Data layer representation of composite/complex news audience segments.
 *
 * These do not have a relationship modifier, they are always implicitly targeted to 'self'
 */
class Model
{
    /** @var News\Model() */
    private $commsNewsArticle;

    /** @var string */
    private $entityType1;

    /** @var null|string */
    private $entityValue1;

    /** @var string */
    private $entityType2;

    /** @var null|string */
    private $entityValue2;


    /**
     * @return null|News\Model
     */
    public function getCommsNewsArticle(): ?News\Model
    {
        return $this->commsNewsArticle;
    }


    /**
     * @param null|News\Model $commsNewsArticle
     * @return $this
     */
    public function setCommsNewsArticle(?News\Model $commsNewsArticle): self
    {
        $this->commsNewsArticle = $commsNewsArticle;
        return $this;
    }


    /**
     * @return string
     */
    public function getEntityType1(): ?string
    {
        return $this->entityType1;
    }


    /**
     * @param string $entityType1
     * @return $this
     */
    public function setEntityType1(string $entityType1): self
    {
        $this->entityType1 = $entityType1;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getEntityValue1(): ?string
    {
        return $this->entityValue1;
    }


    /**
     * @param null|string $entityValue1
     * @return $this
     */
    public function setEntityValue1(?string $entityValue1): self
    {
        $this->entityValue1 = $entityValue1;
        return $this;
    }


    /**
     * @return string
     */
    public function getEntityType2(): ?string
    {
        return $this->entityType2;
    }


    /**
     * @param string $entityType2
     * @return $this
     */
    public function setEntityType2(string $entityType2): self
    {
        $this->entityType2 = $entityType2;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getEntityValue2(): ?string
    {
        return $this->entityValue2;
    }


    /**
     * @param null|string $entityValue2
     * @return $this
     */
    public function setEntityValue2(?string $entityValue2): self
    {
        $this->entityValue2 = $entityValue2;
        return $this;
    }
}
