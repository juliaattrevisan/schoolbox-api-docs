<?php

/**
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Simple;

use Schoolbox\Comms\News;

/**
 * Data layer representation of simple news audience segments
 */
class Model
{
    // Any audience modifier?
    public const RELATIONSHIP_SELF        = 'self';                 // eg, Audience = the user 15
    public const RELATIONSHIP_PARENTS_OF  = 'parents_of';           // eg, Audience = parents of user 15
    public const RELATIONSHIP_TEACHERS_OF = 'teachers_of';          // eg, Audience = teachers of user 15

    /**
     * Returns the set of valid relationship modifiers
     *
     * @return string[]
     */
    public static function getRelationships(): array
    {
        return [
            self::RELATIONSHIP_SELF,
            self::RELATIONSHIP_PARENTS_OF,
            self::RELATIONSHIP_TEACHERS_OF,
        ];
    }

    /** @var News\Model() */
    private $commsNewsArticle;

    /** @var string */
    private $entityType;

    /** @var null|string */
    private $entityValue;

    /** @var string */
    private $relationship;


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
    public function getEntityType(): ?string
    {
        return $this->entityType;
    }


    /**
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getEntityValue(): ?string
    {
        return $this->entityValue;
    }


    /**
     * @param null|string $entityValue
     * @return $this
     */
    public function setEntityValue(?string $entityValue): self
    {
        $this->entityValue = $entityValue;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getRelationship(): ?string
    {
        return $this->relationship;
    }


    /**
     * @param string $relationship
     * @return $this
     */
    public function setRelationship(string $relationship): self
    {
        $this->relationship = $relationship;
        return $this;
    }
}
