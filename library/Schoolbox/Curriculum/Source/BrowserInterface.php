<?php

/**
 * Interface for what a source class should define.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;

interface BrowserInterface
{
    /**
     * Retrieves a collection of learning areas for the curriculum source.
     *
     * @return Collection|LearningArea\Model[]
     */
    public function getLearningAreas();

    /**
     * Returns a collection of learning levels for the curriculum source.
     *
     * @return Collection|LearningLevel\Model[]
     */
    public function getLearningLevels();

    /**
     * Returns a collection of outcomes for the given learning area and level.
     *
     * @param  LearningArea\Model  $area  The learning area to search
     * @param  LearningLevel\Model $level The learning level to search
     *
     * @return GenericCollection|Node\Model[]
     */
    public function getOutcomes(LearningArea\Model $area, LearningLevel\Model $level);
}
