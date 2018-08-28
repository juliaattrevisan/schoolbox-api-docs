<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Learning\AssessmentContext;

/**
 * Factory for building `RowSet` objects.
 */
class Factory
{
    /**
     * @var NodeMapper
     */
    private $nodeMapper;


    /**
     * Constructor
     *
     * @param NodeMapper $nodeMapper
     */
    public function __construct(NodeMapper $nodeMapper)
    {
        $this->nodeMapper = $nodeMapper;
    }


    /**
     * Port of `\Schoolbox\Assessment\Rubric\Criteria\Controller` methods used
     * for creating a rowset for saving.
     *
     * Does not attempt to validate if the rowset is actually saveable.
     *
     * @param AssessmentContext $assessmentContext
     * @param array             $postData
     * @return RowSet
     */
    public function buildFromPostData(AssessmentContext $assessmentContext, array $postData): RowSet
    {
        $rowSet = new RowSet($assessmentContext);

        // Add groups
        foreach ($postData as $tempGroupId => $postedGroup) {
            $group = (new Capability())
                ->setId($postedGroup['id'])
                ->setName($postedGroup['name'] ?? '')
                ->setCurriculumNodes(
                    $this->nodeMapper->findByColumn('id', $postedGroup['curriculumCode'])
                );

            // Add criteria
            foreach ($postedGroup['items'] ?? [] as $tempCriterionId => $postedCriterion) {
                $criterion                                = (new Indicator())
                    ->setId($postedCriterion['id'])
                    ->setName($postedCriterion['name'] ?? '')
                    ->setCurriculumNodes(
                        $this->nodeMapper->findByColumn('id', $postedCriterion['curriculumCode'])
                    );
                $group->getIndicators()[$tempCriterionId] = $criterion;
            }


            $rowSet->getCapabilities()[$tempGroupId] = $group;
        }

        return $rowSet;
    }
}
