<?php
/**
 * NESA Report
 *
 * Generate the NESA report data
 *
 * Used to view the browser version and also populates the csv
 * This way both outputs are derived from the same data source
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Help\Nesa;

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\ProfessionalDevelopment;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;

class Report
{
    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var ProfessionalDevelopment\Mapper
     */
    private $pdMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;

    /**
     * Report constructor.
     *
     * @param AssessmentMapperProxy          $assessmentMapperProxy
     * @param ProfessionalDevelopment\Mapper $pdMapper
     * @param UserAccessMapper               $userAccessMapper
     * @param Rubric\RubricMapper            $rubricMapper
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        ProfessionalDevelopment\Mapper $pdMapper,
        UserAccessMapper $userAccessMapper,
        Rubric\RubricMapper $rubricMapper
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->pdMapper = $pdMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->rubricMapper = $rubricMapper;
    }

    /**
     * Generate report contents in a view-agnostic manner
     *
     * @param array    $state
     * @param int|null $unaccreditedOnly
     * @param array    $indicators
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function generate(array $state, ?int $unaccreditedOnly, array $indicators)
    {
        $assessmentMapperProxy = $this->assessmentMapperProxy;

        /* Find all the folders that have users with Professional Development details
         */
        $prefilter = new Prefilter($this->pdMapper, $this->userAccessMapper);

        $involvement = $prefilter->findInvolvement($state, $unaccreditedOnly);

        /* Acquire NESA data for $folder and the chosen indicators
         */
        $dataSet = (new Acquisition(
            $involvement,
            $indicators,
            $this->rubricMapper,
            $assessmentMapperProxy
        ))->generate();

        /* Process the $dataSet into a visual report
         */
        return (new Aggregation($dataSet))->generate();
    }
}