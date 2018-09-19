<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Comms\News;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits\Model\Id;

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
    public const ENTITY_TYPE_SCHOOL_HOUSE   = 'school_house';       // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_RES_HOUSE      = 'res_house';          // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_YEAR           = 'year';               // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_SERVICE        = 'service';            // Values = ['1.43.3','2.3.*',...]
    public const ENTITY_TYPE_PASTORAL_GROUP = 'pastoral_group';     // Values = [NULL,1,2,...]
    public const ENTITY_TYPE_FOLDER         = 'folder';             // Values = [NULL,1,2,...]

    // Any audience modifier?
    public const RELATIONSHIP_SELF        = 'self';                 // eg, Audience = the user 15
    public const RELATIONSHIP_PARENTS_OF  = 'parents_of';           // eg, Audience = parents of user 15
    public const RELATIONSHIP_TEACHERS_OF = 'teachers_of';          // eg, Audience = teachers of user 15

    // The value of segments targeted to superusers
    public const ENTITY_VALUE_SUPER       = '1';




    /**
     * Returns the set of all known entity types
     *
     * @return string[]
     */
    public static function getEntityTypes(): array
    {
        $entityTypes = self::getSimpleEntityTypes();
        \array_unshift($entityTypes, self::ENTITY_TYPE_COMPOSITE);
        return $entityTypes;
    }


    /**
     * Returns the set of all simple entity types
     *
     * @return string[]
     */
    public static function getSimpleEntityTypes(): array
    {
        return [
            self::ENTITY_TYPE_USER,
            self::ENTITY_TYPE_SUPER,
            self::ENTITY_TYPE_CAMPUS,
            self::ENTITY_TYPE_ROLE,
            self::ENTITY_TYPE_ROLE_TYPE,
            self::ENTITY_TYPE_SCHOOL_HOUSE,
            self::ENTITY_TYPE_RES_HOUSE,
            self::ENTITY_TYPE_YEAR,
            self::ENTITY_TYPE_SERVICE,
            self::ENTITY_TYPE_PASTORAL_GROUP,
            self::ENTITY_TYPE_FOLDER,
        ];
    }


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

    use Id;

    /** @var News\Model() */
    private $commsNewsArticle;

    /** @var null|self */
    private $parent;

    /** @var string */
    private $entityType;

    /** @var null|string */
    private $entityValue;

    /** @var string */
    private $relationship;

    /** @var GenericCollection|self[] */
    private $children;

    /**
     * @throws \UnexpectedValueException
     */
    public function __construct()
    {
        $this->children = new GenericCollection(self::class);
    }


    /**
     * Returns whether this is a composite segment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isComposite(): bool
    {
        if (null === $this->entityType) {
            throw new InsufficientHydrationException('Cannot determine if simple/composite if not hydrated');
        }
        return self::ENTITY_TYPE_COMPOSITE === $this->entityType;
    }


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


    /**
     * @return Model[]|GenericCollection
     */
    public function getChildren(): GenericCollection
    {
        return $this->children;
    }


    /**
     * @param Model[]|Collection $children
     * @return $this
     * @throws \DomainException
     */
    public function setChildren(Collection $children): self
    {
        $this->children->replaceWith($children);
        return $this;
    }
}
