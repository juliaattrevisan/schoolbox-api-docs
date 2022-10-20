<?php

/**
 * Base trait for model classes that have curriculum nodes.
 *
 * Nomeclature: Nodes or codes?
 * Nodes is the internal name, but codes are their name in user interfaces.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Node\Traits;

use Schoolbox\Curriculum\Node\Model;
use Schoolbox\Collection;

trait HasCurriculumNodes
{
    /**
     * A collection of CurriculumNode instances
     *
     * @var Collection|Model[]
     */
    protected $curriculumNodes;

    /**
     * @return Collection|Model[]
     */
    public function getCurriculumNodes()
    {
        return $this->curriculumNodes;
    }

    /**
     * @param  Collection|Model[] $curriculumNodes
     *
     * @return $this
     */
    public function setCurriculumNodes($curriculumNodes)
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }

    /**
     * Returns just the Curriculum nodes that do not have an empty code.
     * usually these are parent objects, or titles
     * @return Collection|Model[]
     */
    public function getViewableCurriculumNodes()
    {
        return $this->curriculumNodes->filter(
            function ($node) {
                if ($node->getCode() !== '') {
                    return true;
                } else {
                    return false;
                }
            }
        );
    }
}
