<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\CreateCollection;

use function array_diff;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function implode;

/**
 * A brick is logically composed of:
 * * a set of dimensions, and
 * * a set of relationship modifiers
 *
 * This class roughly corresponds to the javascript bricks displayed on the News create/update page.
 *
 * This is a simpler version than that algorithm, as the JS algorithm provides more flexibility than we have
 * ended up providing to users. Specifically having modifiers at both the brick and segment levels.
 *
 * A major function of Bricks is that they can be combined with other Bricks using degrees-of-freedom (DOF) analysis.
 *
 * @method static GenericCollection|Brick[] createCollection()
 */
class Brick
{
    use CreateCollection;

    /** @var Dimension[] */
    private $dimensions;

    /** @var string[] */
    private $relationships;


    /**
     * @param array  $dimensions
     * @param string $relationship
     * @throws InvalidArgumentException If no dimensions are supplied
     */
    public function __construct(array $dimensions, string $relationship)
    {
        if (!count($dimensions)) {
            throw new InvalidArgumentException("A brick must have 1 or more dimensions");
        }
        $this->dimensions    = $dimensions;
        $this->relationships = [$relationship];
    }


    /**
     * Returns whether this Brick is a superset of another Brick
     *
     * Returns true if bricks are equal, or other brick is a subset of this brick
     *
     * @param Brick $other
     * @return bool
     */
    public function contains(self $other): bool
    {
        // Does other brick cover more relationships?
        if (count(array_diff($other->relationships, $this->relationships))) {
            return false;
        }

        // This brick contains another brick if all dimensions on this brick are a superset of a dimension
        // on the other brick.
        // eg, this brick [F:1,2 ∩ R:3,4] and other brick [F:1 ∩ R:3,4 ∩ C:6].
        // Both dimensions are a superset of a other dimension, the 3rd dimension just reduces the size of the other brick.
        foreach ($this->dimensions as $thisDimension) {
            $isSuperset = false;
            foreach ($other->dimensions as $otherDimension) {
                if ($thisDimension->contains($otherDimension)) {
                    $isSuperset = true;
                    break;
                }
            }
            if (!$isSuperset) {
                return false;
            }
        }
        return true;
    }


    /**
     * Try to add another brick to this brick via a suitable addition of 1 of the dimensions
     *
     * @param Brick $other
     * @return bool Whether we were able to add the other brick
     */
    public function addBrick(self $other): bool
    {
        if (count($this->dimensions) !== count($other->dimensions)) {
            return false;
        }

        // Does other brick have more relationship modifiers?
        $relationshipDof = count(array_diff($other->relationships, $this->relationships)) ? 1 : 0;
        $brickDof        = $relationshipDof;

        // Strip any 0-DOF dimension pairs from analysis.
        [$thisDimensionsCopy, $otherDimensionsCopy] = $this->remove0DofPairs($this->dimensions, $other->dimensions);

        // The 1-DOF dimension pair
        /** @var null|Dimension $thisCandidate */
        $thisCandidate = null;
        /** @var null|Dimension $otherCandidate */
        $otherCandidate = null;


        // Find the 1-DOF dimension pair
        foreach ($thisDimensionsCopy as $thisDimension) {
            $minDof = 2;
            foreach ($otherDimensionsCopy as $otherDimension) {
                $minDof = min($minDof, $thisDimension->computeDof($otherDimension));
                if ($minDof === 1) {
                    $thisCandidate  = $thisDimension;
                    $otherCandidate = $otherDimension;
                }
            }
            // Is other dimension not equal to, or unable to be combined with, a dimension on this block?
            if ($minDof === 2) {
                return false;
            }
            $brickDof += $minDof;
            // Are bricks different on too many dimensions? (including relationship)
            if ($brickDof >= 2) {
                return false;
            }
        }
        // Are bricks equal?
        if ($brickDof === 0) {
            return false;
        }
        if ($relationshipDof) {
            $this->relationships = array_unique(array_merge($this->relationships, $other->relationships));
            return true;
        }
        return $thisCandidate->addDimension($otherCandidate);
    }


    /**
     * Removes any 0-DOF pairs from collections.
     *
     * Consider [F:X^S ∩ F:Y^P] +
     *          [F:X^S ∩ F:X^P]
     * Here we have DOFs [0 + 1] or [1 + 2] (depending if we combine dimensions 1+1,2+2 or 1+2,2+1
     * Note that F:X^P is 1-DOF from both F:X^S and F:Y^P
     * If we just blindly combined F:X^P and F:X^S, we would be in trouble because the remaining
     * pair F:X^S and F:Y^P are 2-DOF.
     *
     * We must therefore remove any 0-DOF from analysis
     *
     * @param Dimension[] $dimensions1
     * @param Dimension[] $dimensions2
     * @return Dimension[][]
     */
    private function remove0DofPairs(array $dimensions1, array $dimensions2): array
    {
        foreach ($dimensions1 as $idx1 => $dimension1) {
            foreach ($dimensions2 as $idx2 => $dimension2) {
                if (!$dimension1->computeDof($dimension2)) {
                    unset($dimensions1[$idx1], $dimensions2[$idx2]);
                    break;
                }
            }
        }
        return [$dimensions1, $dimensions2];
    }


    /**
     * @return Dimension[]
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }


    /**
     * @return string[]
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }


    /**
     * Returns a string representation of this brick, eg "[folder:1,2 ∩ role:3]^(self,parents_of)"
     *
     * @return string
     */
    public function __toString()
    {
        $dimensionStrings = implode(' ∩ ', array_map(static function (Dimension $dimension): string {
            return $dimension->__toString();
        }, $this->dimensions));
        $relationships    = implode(',', $this->relationships);
        return "[$dimensionStrings]^($relationships)";
    }
}
