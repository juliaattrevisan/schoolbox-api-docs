<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

class Model
{
    // A composite audience segment is composed of the intersection of 2 or more simple segments
    public const ENTITY_TYPE_COMPOSITE      = 'composite';          // Values = [NULL]

    // Simple audience segments
    public const ENTITY_TYPE_USER           = 'user';               // Values = [1,2,...]
    public const ENTITY_TYPE_SUPER          = 'super';              // Values = [1]
    public const ENTITY_TYPE_CAMPUS         = 'campus';             // Values = [1,2,...]
    public const ENTITY_TYPE_ROLE           = 'role';               // Values = [1,2,...]
    public const ENTITY_TYPE_ROLE_TYPE      = 'role_type';          // Values = []
    public const ENTITY_TYPE_HOUSE          = 'house';              // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_RES_HOUSE      = 'res_house';          // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_YEAR           = 'year';               // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_SERVICE        = 'service';            // Values = ['1.43.3','2.3.*',...]
    public const ENTITY_TYPE_PASTORAL_GROUP = 'pastoral_group';     // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_GROUP          = 'group';              // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_CLASS          = 'class';              // Values = [NULL,'ENG34','ABC123',...]

    // Any audience modifier?
    public const RELATIONSHIP_SELF        = 'self';                 // eg, Audience = the user 15
    public const RELATIONSHIP_PARENTS_OF  = 'parents_of';           // eg, Audience = parents of user 15
    public const RELATIONSHIP_TEACHERS_OF = 'teachers_of';          // eg, Audience = teachers of user 15

    /** @var int */
    private $id;

    /** @todo declare type */
    private $commsNewsArticle;

    /** @var null|self */
    private $parent;

    /** @var string */
    private $entityType;

    /** @var null|string */
    private $entityValue;

    /** @var string */
    private $relationship;


    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }


    /**
      * @todo declare return type
     */
    public function getCommsNewsArticle()
    {
        return $this->commsNewsArticle;
    }


    /**
     * @param $commsNewsArticle
     * @return $this
     * @todo declare nullable parameter type
     */
    public function setCommsNewsArticle($commsNewsArticle): self
    {
        $this->commsNewsArticle = $commsNewsArticle;
        return $this;
    }


    /**
     * @return null|Model
     */
    public function getParent(): ?Model
    {
        return $this->parent;
    }


    /**
     * @param null|Model $parent
     * @return $this
     */
    public function setParent(?Model $parent): self
    {
        $this->parent = $parent;
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
