<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Comms\News\Audience;
use Schoolbox\GenericCollection;
use LogicException;
use Schoolbox\Mapper;
use DomainException;
use UnexpectedValueException;

/**
 * Responsible for converting between audience segment models and their string representation.
 * Must match the implementation of the JS version of this class.
 */
class Codec
{

    private const DELIMITER_SELECTIZE = ',';
    private const DELIMITER_COMPOSITE = ';';
    private const DELIMITER_SIMPLE = ':';
    private const DELIMITER_CHILDREN = '.';

    /**
     * Returns a string-representation of this segment
     *
     * @param Audience\Model $segment
     * @return string
     * @throws Mapper\Exception\InsufficientHydrationException
     */
    public function encode(Audience\Model $segment): string
    {
        return $segment->isSimple()
            ? $this->encodeSimpleSegment($segment)
            : $this->encodeComposite($segment);
    }


    /**
     * Returns a string-representation of a simple segment's logical attributes
     *
     * @param string      $entityType
     * @param null|string $entityValue
     * @param string      $modifier
     * @return string
     */
    public function encodeSimpleAttributes(string $entityType, ?string $entityValue, string $modifier): string
    {
        return $this->encodeSimpleParts($entityType, $entityValue, $modifier);
    }


    /**
     * Converts an encoded string into an Audience Segment Model
     *
     * @param string $encodedSegment
     * @return Audience\Model
     * @throws DomainException
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     */
    public function decode(string $encodedSegment): Audience\Model
    {
        return 0 !== strpos($encodedSegment, Strategy\Composite::getEntityType())
            ? $this->decodeSimple($encodedSegment)
            : $this->decodeComposite($encodedSegment);
    }


    /**
     * Takes a value string $value and encodes it
     *
     * @param null|string $value
     * @return string
     */
    public function encodeValue(?string $value): string
    {
        // This scheme supports at least the following values:
        // [':', ',', null, 'null', '1', '', 1, '"', "'", '\\', '/', '<', '&', '!@*(@*#&)!+_!+!@']
        return \base64_encode(\json_encode($value));
    }


    /**
     * Converts a selectize value to a set of structured audience segments
     *
     * @param string $selectizeValues
     * @return GenericCollection
     * @throws DomainException
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     */
    public function decodeSelectizeValues(string $selectizeValues): GenericCollection
    {
        $rootSegments = new GenericCollection(Audience\Model::class);

        $trimmed = \trim($selectizeValues);

        if('' === $trimmed){
            return $rootSegments;
        }

        foreach (\explode(self::DELIMITER_SELECTIZE, $trimmed) as $encodedSegment) {
            // Cannot index, as will not have id set
            $rootSegments->add($this->decode($encodedSegment));
        }

        return $rootSegments;
    }


    /**
     * Helper to ensure consistent encoding scheme, regardless of data source
     *
     * @param string      $entityType
     * @param null|string $entityValue
     * @param string      $modifier
     * @return string
     */
    private function encodeSimpleParts(string $entityType, ?string $entityValue, string $modifier): string
    {
        $parts = [
            $entityType,
            $this->encodeValue($entityValue),
            $modifier,
        ];

        return \implode(self::DELIMITER_SIMPLE, $parts);
    }


    /**
     * Takes an encoded value string $value and decodes it
     *
     * @param string $encodedValue
     * @return null|string
     */
    private function decodeValue(string $encodedValue): ?string
    {
        return \json_decode(\base64_decode($encodedValue));
    }


    /**
     * Encodes a simple segment
     *
     * @param Audience\Model $segment
     * @return string
     */
    private function encodeSimpleSegment(Audience\Model $segment): string
    {
        $parts = [
            $segment->getEntityType(),
            $this->encodeValue($segment->getEntityValue()),
            $segment->getRelationship(),
        ];

        return \implode(self::DELIMITER_SIMPLE, $parts);
    }


    /**
     * Decodes a encoded string into a simple audience segment
     *
     * @param string $encodedSegment
     * @return Audience\Model
     */
    private function decodeSimple(string $encodedSegment): Audience\Model
    {
        [$type, $encodedValue, $modifier] = \explode(self::DELIMITER_SIMPLE, $encodedSegment);

        return (new Audience\Model())
                                   ->setEntityType($type)
                                   ->setEntityValue($this->decodeValue($encodedValue))
                                   ->setRelationship($modifier);
    }


    /**
     * Encodes a composite segment
     *
     * @param Audience\Model $segment
     * @return string
     */
    private function encodeComposite(Audience\Model $segment): string
    {
        $childParts = [];
        foreach ($this->getSortedChildSegments($segment->getChildren()) as $childSegment) {
            $childParts[] = $this->encodeSimpleSegment($childSegment);
        }

        $parts = [
            $segment->getEntityType(),         // need to put 'composite' first to easily detect composites
            $segment->getRelationship(),
            \implode(self::DELIMITER_CHILDREN, $childParts),
        ];

        return implode(self::DELIMITER_COMPOSITE, $parts);
    }


    /**
     * Decodes an encoded composite segment
     *
     * @param string $encodedSegment
     * @return Audience\Model
     * @throws DomainException
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     */
    private function decodeComposite(string $encodedSegment): Audience\Model
    {
        [$type, $modifier, $encodedChildren] = \explode(self::DELIMITER_COMPOSITE, $encodedSegment);

        $children = new GenericCollection(Audience\Model::class);
        foreach (\explode(self::DELIMITER_CHILDREN, $encodedChildren) as $encodedChild) {
            $children->add($this->decodeSimple($encodedChild));
        }

        // Check whether all children are identical. If so, reduce to equivalent simple form.
        /** @var null|Audience\Model $firstSegment */
        $firstSegment       = null;
        $distinctChildFound = false;
        foreach ($children as $child) {
            if (!$firstSegment) {
                $firstSegment = $child;
            } elseif (!$firstSegment->equals($child)) {
                $distinctChildFound = true;
                break;
            }
        }
        if (!$distinctChildFound) {
            $effectiveModifier = $modifier === Audience\Model::RELATIONSHIP_SELF ? $firstSegment->getRelationship() : $modifier;
            return (new Audience\Model())
                                       ->setEntityType($firstSegment->getEntityType())
                                       ->setEntityValue($firstSegment->getEntityValue())
                                       ->setRelationship($effectiveModifier);
        }

        return (new Audience\Model())
                                   ->setEntityType($type)
                                   ->setChildren($children)
                                   ->setRelationship($modifier);
    }


    /**
     * Returns a set of audience segments sorted. Does not mutate passed-in collection
     *
     * @param GenericCollection|Audience\Model[] $segments
     * @return GenericCollection|Audience\Model[]
     */
    private function getSortedChildSegments(GenericCollection $segments): GenericCollection
    {
        $unsorted = $segments->shallowClone();

        return $unsorted->sortByCallback(function (Audience\Model $left, Audience\Model $right) {
            // first sort by entityType
            if ($left->getEntityType() < $right->getEntityType()) {
                return -1;
            }
            if ($left->getEntityType() > $right->getEntityType()) {
                return +1;
            }
            // Yes, it's possible to target the intersection of two entities of the same type, eg pastoral groups which are 1:N with users.
            // Also need to encode first so we match the client-side algorithm
            $encodedLeft  = $this->encodeValue($left->getEntityValue());
            $encodedRight = $this->encodeValue($right->getEntityValue());
            if ($encodedLeft < $encodedRight) {
                return -1;
            }
            if ($encodedLeft > $encodedRight) {
                return +1;
            }
            // Yes, it's possible to target the intersection of two entities of the same type, and value.
            // Eg must be a member of House:X, and a parent of a student who is a member of House:X
            if ($left->getRelationship() < $right->getRelationship()) {
                return -1;
            }
            if ($left->getRelationship() > $right->getRelationship()) {
                return +1;
            }
            // If we get here, something has gone wrong.
            throw new LogicException("Unable to distinguish composite segment children for sort. EntityType: '{$left->getEntityType()}', EntityValue: '{$left->getEntityValue()}'");
        }, true);
    }
}
