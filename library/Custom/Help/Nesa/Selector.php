<?php
/**
 * NESA Rubric Selector
 *
 * Generate a list of Descriptors that were used in a particular Course Specification
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Nesa;

use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

class Selector
{
    /**
     * @var int
     */
    private $courseSpecificationId;

    /**
     * @var UnitSpecificationMapper
     */
    private $unitSpecificationMapper;

    /**
     * @var Mapper
     */
    private $criterionMapper;

    /**
     * @var Mapper
     */
    private $rubricDescriptorMapper;

    /**
     * NesaSelector constructor.
     *
     * @param int                     $courseSpecificationId The Teacher Course upon which we report
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @param Mapper                  $criterionMapper
     * @param Mapper                  $rubricDescriptorMapper
     */
    public function __construct(
        int $courseSpecificationId,
        UnitSpecificationMapper $unitSpecificationMapper,
        Mapper $criterionMapper,
        Mapper $rubricDescriptorMapper
    ) {
        $this->courseSpecificationId = $courseSpecificationId;
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        $this->criterionMapper = $criterionMapper;
        $this->rubricDescriptorMapper = $rubricDescriptorMapper;
    }

    /**
     * Generate a list of descriptors that were specified in the course $courseSpecificationId
     *
     * @return \Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model[]|\Schoolbox\GenericCollection
     */
    public function generate()
    {
        /* Find all the unit specifications. We'll use those to figure out what Rubric Criteria records exist
         * for the course spec, then accumulate all their ids
         */
        $allUnitSpecifications = $this->unitSpecificationMapper->findAllByCourseId($this->courseSpecificationId);

        /* Find all the criteria used in each unit specification
         *
         * These are cloned per course import so we need to specifically get all usages of the original
         * There seems to be no relation between the cloned criteria and their original progenitor
         */
        $parentIds = [];

        foreach ($allUnitSpecifications as $currentUnitSpecification) {
            $parentIds[] = $currentUnitSpecification->getFolder()->getId();
        }

        $allSpecCrits = $this->criterionMapper->findByColumn('parent_id', $parentIds);

        /* Now find all the descriptors contained in the spec
         */
        $criterionIds = [];

        foreach ($allSpecCrits as $currentSpecCrit) {
            $criterionIds[] = $currentSpecCrit->getId();
        }

        $allSpecDescriptors = $this->rubricDescriptorMapper->findByColumn('criterion_id', $criterionIds);

        /* $allSpecDescriptors is now a list of all the descriptors used in the course spec.
         * From here we can choose which descriptors we want to include in the report
         *
         * De-dupe everything (is there a smarter way? Maybe a custom query but bleeeah)
         */
        $seenCombination = [];

        $deduped = [];

        foreach ($allSpecDescriptors as $descriptorId => $current) {
            $index = "{$current->getDescription()}{$current->getIndicator()->getName()}{$current->getIndicator()->getGroup()->getName()}";

            /* Mark it as seen so we can ignore it later
             */
            if (!isset($seenCombination[$index])) {
                $seenCombination[$index] = true;

                $deduped[$descriptorId] = $current;
            }
        }

        return $deduped;
    }
}
