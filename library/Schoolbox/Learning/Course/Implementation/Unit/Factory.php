<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Implementation\Unit;

use Schoolbox\Learning\Course\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

/**
 * A factory for Unit Implementation objects.
 *
 * @package Schoolbox\Learning\Course\Implementation\Unit
 */
class Factory
{
    /**
     * Builds a Unit Implementation out of the given Unit Specification.
     *
     * @param UnitSpecification $unitSpecification
     * @return Model
     */
    public function buildFromSpecification(UnitSpecification $unitSpecification)
    {
        $implementation = new UnitImplementation();
        foreach ($unitSpecification->getAssessables() as $assessable) {
            // Maybe each assessable has a clone method?
            $newAssessable = clone $assessable;
            $newAssessable->setId(null);
            $implementation->getAssessables()->add($newAssessable);
        }
        return $implementation;
    }
}
