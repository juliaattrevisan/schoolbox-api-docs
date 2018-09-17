<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\Segment\Codec;
use Schoolbox\GenericCollection;
use Schoolbox\Comms\News;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Comms\News\Audience\Segment\Strategy;

class Model
{
    // A composite audience segment is composed of the intersection of 2 or more simple segments
    public const ENTITY_TYPE_COMPOSITE      = 'composite';          // Values = [NULL]

    // Simple audience segments - TODO deprecated. Use the IStrategy::getEntityType() interface instead.
    /** @deprecated */
    public const ENTITY_TYPE_USER           = Strategy\User::ENTITY_TYPE;               // Values = [1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_SUPER          = Strategy\Super::ENTITY_TYPE;              // Values = [1]
    /** @deprecated */
    public const ENTITY_TYPE_CAMPUS         = Strategy\Campus::ENTITY_TYPE;             // Values = [1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_ROLE           = Strategy\Role::ENTITY_TYPE;               // Values = [1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_ROLE_TYPE      = Strategy\RoleType::ENTITY_TYPE;           // Values = []
    /** @deprecated */
    public const ENTITY_TYPE_SCHOOL_HOUSE   = Strategy\SchoolHouse::ENTITY_TYPE;        // Values = [NULL,1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_RES_HOUSE      = Strategy\ResHouse::ENTITY_TYPE;           // Values = [NULL,1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_YEAR           = Strategy\Year::ENTITY_TYPE;               // Values = [NULL,1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_SERVICE        = Strategy\Service::ENTITY_TYPE;            // Values = ['1.43.3','2.3.*',...]
    /** @deprecated */
    public const ENTITY_TYPE_PASTORAL_GROUP = Strategy\PastoralGroup::ENTITY_TYPE;      // Values = [NULL,1,2,...]
    /** @deprecated */
    public const ENTITY_TYPE_FOLDER         = Strategy\Folder::ENTITY_TYPE;             // Values = [NULL,1,2,...]

    // Any audience modifier?
    public const RELATIONSHIP_SELF        = 'self';                 // eg, Audience = the user 15
    public const RELATIONSHIP_PARENTS_OF  = 'parents_of';           // eg, Audience = parents of user 15
    public const RELATIONSHIP_TEACHERS_OF = 'teachers_of';          // eg, Audience = teachers of user 15

    // The value of segments targeted to superusers
    public const ENTITY_VALUE_SUPER       = '1';


    public const DEPTH_SEGMENT_TREE_MAX   = 1;


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


    public function __construct()
    {
        /** @noinspection PhpUnhandledExceptionInspection (impossible as we're not adding items) */
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
     * Returns whether this is a simple segment
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isSimple(): bool
    {
        return !$this->isComposite();
    }


    /**
     * Returns whether this segment is logically equivalent to another segment.
     *
     * Ignores ID and child permutations
     *
     * @param Model $other
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function equals(Model $other): bool
    {
        if ($this->isSimple() !== $other->isSimple()) {
            return false;
        }


        if ($this->getEntityType() !== $other->getEntityType() ||
            $this->getEntityValue() !== $other->getEntityValue() ||
            $this->getRelationship() !== $other->getRelationship()) {
            return false;
        }

        // Simple case
        if ($this->isSimple()) {
            // If we passed the above test, that's sufficient
            return true;
        }

        // Composite case. Need to check children are the same combination (but not permutation)
        if ($this->children->count() !== $other->children->count()) {
            return false;
        }

        // Ensure each child is found in the other composite
        foreach ($this->children as $thisChild) {
            $otherChildMatchFound = false;
            foreach ($other->children as $otherChild) {
                if ($thisChild->equals($otherChild)) {
                    $otherChildMatchFound = true;
                    break;
                }
            }
            if (!$otherChildMatchFound) {
                return false;
            }
        }
        return true;
    }

    // Getters/Setters


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
