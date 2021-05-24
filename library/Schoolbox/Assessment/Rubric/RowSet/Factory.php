<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * Port of `\Schoolbox\Assessment\Rubric\Manager\Controller` methods used
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

        // Add capabilities
        foreach ($postData as $tempCapabilityId => $postedCapability) {
            $capability = (new Capability())
                ->setId(filter_var($postedCapability['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))
                ->setName($postedCapability['name'] ?? '')
                ->setCurriculumNodes(
                    $this->nodeMapper->findByColumn('id', $postedCapability['curriculumCode'])
                );

            // Add indicators
            foreach ($postedCapability['items'] ?? [] as $tempIndicatorId => $postedIndicator) {
                $indicator = (new Indicator())
                    ->setId(filter_var($postedIndicator['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))
                    ->setName($postedIndicator['name'] ?? '')
                    ->setCurriculumNodes(
                        $this->nodeMapper->findByColumn('id', $postedIndicator['curriculumCode'])
                    );
                $capability->getIndicators()[$tempIndicatorId] = $indicator;
            }


            $rowSet->getCapabilities()[$tempCapabilityId] = $capability;
        }

        return $rowSet;
    }
}
