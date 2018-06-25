<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

class ValidSegment
{

    /** @var string[] */
    private $invalidReasons;


    /**
     * Asserts an audience segments is valid, throws exception if not.
     *
     * @param Model $segment
     * @throws InvalidSegmentException
     * @throws \InvalidArgumentException
     */
    public function assertValid(Model $segment): void
    {
        $this->validate($segment);
        if (\count($this->invalidReasons)) {
            throw new InvalidSegmentException($this->getInvalidReasons());
        }
    }


    /**
     * Non-throwing version to check if an audience segment is valid
     *
     * @param Model $segment
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isValid(Model $segment): bool
    {
        $this->validate($segment);
        return !\count($this->invalidReasons);
    }


    /**
     * Returns the reasons why the last checked segment was invalid. Will be empty string if segment was valid.
     *
     * @return string
     */
    public function getInvalidReasons(): string
    {
        return \implode('. ', $this->invalidReasons);
    }


    /**
     * @param Model $segment
     * @throws \InvalidArgumentException
     */
    private function validate(Model $segment): void
    {
        $this->invalidReasons = [];
        $continue             = true;

        if ($segment->getCommsNewsArticle() && $segment->getParent()) {
            $this->invalidReasons[] = 'Segment cannot be directly linked to article and parent segment simultaneously';
        }
        if (!$segment->getCommsNewsArticle() && !$segment->getParent()) {
            $this->invalidReasons[] = 'Segment must be linked to article or parent segment';
        }

        if (null === $segment->getEntityType()) {
            $this->invalidReasons[] = 'Entity type is NULL, cannot validate';
            // Cannot continue validate
            $continue = false;
        }

        if (null === $segment->getRelationship()) {
            $this->invalidReasons[] = 'Relationship modifier is NULL, cannot validate';
            // Cannot continue validate
            $continue = false;
        }

        if (!$continue) {
            return;
        }

        if (Model::ENTITY_TYPE_COMPOSITE === $segment->getEntityType() && !$segment->getChildren()->count()) {
            $this->invalidReasons[] = 'Composite segments must have 1 or more children';
        }

        $treeHeight = $this->getTreeHeight($segment);
        $maxNestingLevels = BusinessRules::DEPTH_SEGMENT_TREE_MAX;
        if ($treeHeight > $maxNestingLevels) {
            $this->invalidReasons[] = "Segment nesting level: '{$treeHeight}' exceeds max depth: '{$maxNestingLevels}'";
        }

        $validEntityTypes = BusinessRules::getValidEntities($segment->getRelationship());
        if (!\in_array($segment->getEntityType(), $validEntityTypes, true)) {
            $this->invalidReasons[] = "Segments targeted to entity: '{$segment->getEntityType()}' cannot have relationship modifier: '{$segment->getRelationship()}'";
        }

        if (!\in_array($segment->getRelationship(), BusinessRules::getValidRelationships($segment), true)) {
            $this->invalidReasons[] = "Invalid relationship modifier: '{$segment->getRelationship()}'";
        }

        if(null === $segment->getEntityValue() && !BusinessRules::canTargetNullEntity($segment->getEntityType())){
            $this->invalidReasons[] = "Segments targeted to type: '{$segment->getEntityType()}' cannot have NULL value";
        }
    }

    private function getTreeHeight(Model $segment): int
    {
        // Find the parent
        $parent = $segment;
        while ($parent->getParent() instanceof Model) {
            $parent = $parent->getParent();
        }

        return $this->getSegmentHeightRecursive($parent);
    }

    private function getSegmentHeightRecursive(Model $segment): int
    {
        $maxHeight = 0;
        foreach($segment->getChildren() as $childSegment){
            $maxHeight = \max($maxHeight, 1 + $this->getSegmentHeightRecursive($childSegment));
        }
        return $maxHeight;
    }
}
