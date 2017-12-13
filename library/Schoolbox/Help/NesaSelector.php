<?php
/**
 * NesaSelector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;

class NesaSelector
{

    /**
     * NesaSelector constructor.
     *
     * @param int $courseSpecificationId The Teacher Course upon which we report
     */
    public function __construct(int $courseSpecificationId)
    {
        $this->courseSpecificationId = $courseSpecificationId;
    }

    /**
     * Generate a list of descriptors that were specified in the course $courseSpecificationId
     *
     * @return Rubric\Descriptor[]|\Schoolbox\GenericCollection
     */
    public function generate()
    {
        /* Find all the unit specifications. We'll use those to figure out what Rubric Criteria records exist
         * for the course spec, then accumulate all their ids
         */
        $allUnitSpecifications = UnitSpecificationMapper::getInstance()->findAllByCourseId($this->courseSpecificationId);

        /* Find all the criteria used in each unit specification
         *
         * These are cloned per course import so we need to specifically get all usages of the original
         * There seems to be no relation between the cloned criteria and their original progenitor
         */
        $parentIds = [];

        foreach ($allUnitSpecifications as $currentUnitSpecification) {
            $parentIds[] = $currentUnitSpecification->getFolder()->getId();
        }

        $allSpecCrits = CriterionMapper::getInstance()->findByColumn('parent_id', $parentIds);

        /* Now find all the descriptors contained in the spec
         */
        $criterionIds = [];

        foreach ($allSpecCrits as $currentSpecCrit) {
            $criterionIds[] = $currentSpecCrit->getId();
        }

        $allSpecDescriptors = Rubric\DescriptorMapper::getInstance()->findByColumn('criterion_id', $criterionIds);

        /* $allSpecDescriptors is now a list of all the descriptors used in the course spec.
         * From here we can choose which descriptors we want to include in the report
         */
        return $allSpecDescriptors;
    }
}