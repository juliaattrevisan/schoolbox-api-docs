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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Help\Nesa;

use Schoolbox\Assessment\Rubric\DataModel;
use Schoolbox\UserMapper;

class Report
{
    /**
     * @var DataModel\MapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var DataModel\DataSource
     */
    private $rubricMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Report constructor.
     *
     * @param DataModel\MapperProxy $assessmentMapperProxy
     * @param DataModel\DataSource  $rubricMapper
     * @param UserMapper            $userMapper
     */
    public function __construct(
        DataModel\MapperProxy $assessmentMapperProxy,
        DataModel\DataSource $rubricMapper,
        UserMapper $userMapper
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper = $rubricMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Generate report contents in a view-agnostic manner
     * Acquire NESA data for $folder and the chosen indicators
     *
     * @param array $states
     * @param int   $unaccreditedOnly
     * @param array $indicators
     * @return array
     */
    public function generate(array $states, int $unaccreditedOnly, array $indicators) :array
    {
        $dataSet = (new Acquisition(
            $states,
            $unaccreditedOnly,
            $indicators,
            $this->rubricMapper,
            $this->assessmentMapperProxy,
            $this->userMapper
        ))->generate();

        /* Process the $dataSet into a visual report
         */
        return (new Aggregation($dataSet))->generate();
    }
}
