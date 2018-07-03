<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Validation;

use Specification;
use Schoolbox\Comms\News\Audience;

/**
 * Responsible for building valid audience segment specifications
 */
class Factory
{

    /** @var Audience\BusinessRules */
    private $businessRules;


    /**
     * @param Audience\BusinessRules $businessRules
     */
    public function __construct(Audience\BusinessRules $businessRules)
    {
        $this->businessRules = $businessRules;
    }


    /**
     * Defines what is a valid audience segment
     *
     * @return Specification\ISpecification
     */
    public function buildValidSegmentSpecification(): Specification\ISpecification
    {
        // Candidate must be of correct class
        $instanceOfSegment = new Specification\IsInstanceOf(Audience\Model::class);

        // Segment must be linked to an article or parent segment, but not both
        $validContext = new Specification\ExclusiveOr([
            new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_COMMS_NEWS_ARTICLE_ID), null),
            new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_PARENT_ID), null),
        ]);

        // All segments must have an entity type
        $hasEntityType = new Specification\Negation(new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_ENTITY_TYPE), null));

        // All segments must have a relationship modifier
        $hasRelationship = new Specification\Negation(new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_RELATIONSHIP), null));

        // Segment's tree height must be valid
        $validTreeHeight = new ValidTreeHeight($this->businessRules->getMaxSegmentTreeHeight());

        // Composite segments:
        // * must have children
        // * cannot be children of another composite segment
        $compositeWithChildren = new Specification\Conjunction([
            new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_ENTITY_TYPE), Audience\Model::ENTITY_TYPE_COMPOSITE),
            new HasChildren(),
            new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_PARENT_ID), null),
        ]);

        // Simple segments:
        // * cannot have children
        $simpleWithoutChildren = new Specification\Conjunction([
            new Specification\Negation(new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_ENTITY_TYPE), Audience\Model::ENTITY_TYPE_COMPOSITE)),
            new Specification\Negation(new HasChildren()),
        ]);
        $compositeOrSimple     = new Specification\ExclusiveOr([$compositeWithChildren, $simpleWithoutChildren]);

        // Ensure the relationship is valid for the segment's entity type
        $validEntityRelationship = new ValidRelationship($this->businessRules);

        // Either not targeted to NULL, or targeted to NULL but that's valid for the segment's entity
        $notInvalidNull = new Specification\Disjunction([
            new Specification\Negation(new Specification\Equals($this->columnToProperty(Audience\Mapper::FIELD_ENTITY_TYPE), null)),
            new ValidNullEntity($this->businessRules),
        ]);

        return new Specification\Conjunction([
            $instanceOfSegment,
            $validContext,
            $hasEntityType,
            $hasRelationship,
            $validTreeHeight,
            $compositeOrSimple,
            $validEntityRelationship,
            $notInvalidNull,
        ]);
    }


    /**
     * Returns a database field in the likely corresponding property name
     *
     * @param string $column
     * @return string
     */
    private function columnToProperty(string $column): string
    {
        // Strip '_id' suffix
        return score2camel(preg_replace('/_id$/', '', $column));
    }
}
