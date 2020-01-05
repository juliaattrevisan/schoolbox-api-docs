<?php
/**
 * help.schoolbox.com.au
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Nesa;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as RubricDescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\ProfessionalDevelopment;
use Schoolbox\UserMapper;
use function ini_set;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var Specification\Mapper
     */
    private $courseSpecificationMapper;

    /**
     * @var RubricDataSource
     */
    private $rubricMapper;

    /**
     * @var ProfessionalDevelopment\Mapper
     */
    private $pdMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Controller constructor.
     *
     * @param Error\Mapper                   $errorMapper
     * @param Specification\Mapper           $courseSpecificationMapper
     * @param RubricDataSource               $rubricMapper
     * @param ProfessionalDevelopment\Mapper $pdMapper
     * @param UserMapper                     $userMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Specification\Mapper $courseSpecificationMapper,
        RubricDataSource $rubricMapper,
        ProfessionalDevelopment\Mapper $pdMapper,
        UserMapper $userMapper
    ) {
        ini_set('max_execution_time', 300);
        $this->errorMapper = $errorMapper;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->rubricMapper = $rubricMapper;
        $this->pdMapper = $pdMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * NESA Report rubric selection
     *
     * Selection of capabilities, indicators, descriptors which are used to configure a report output
     *
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getNesa()
    {
        /* If the hardcoded course is not found, for some reason
         * Add an error code for this later if it becomes user-configurable
         * For now, we hardcode this manually; is reasonable to assume it exists
         */
        try {
            $courseSpecification = $this->courseSpecificationMapper->findById(Acquisition::NESA_COURSE_SPEC_ID);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        $selector = new Selector(
            Acquisition::NESA_COURSE_SPEC_ID,
            Specification\Unit\Mapper::getInstance(),
            RubricIndicatorMapper::getInstance(),
            RubricDescriptorMapper::getInstance()
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@helpNesa/selector.twig', [
            'course' => $courseSpecification,
            'states' => $this->pdMapper->getAvailableStates(),
            'descriptors' => $selector->generate()
        ]));
    }

    /**
     * NESA Report generation
     *
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postNesa()
    {
        $start = microtime(true);

        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        $report = (new Report(
            $assessmentMapperProxy,
            $this->rubricMapper,
            $this->userMapper
        ))->generate(
            $this->getRequest()->post['state'],
            (int)$this->getRequest()->post['unaccreditedOnly'],
            $this->getRequest()->post['indicators']
        );

        $end = microtime(true);

        /* There are dupes but we only match on text content
         */
        $indicators = array_filter(array_unique($this->getRequest()->post['indicators']));

        /* The usual output
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@helpNesa/report.twig', [
            'report' => $report,
            'duration' => number_format($end - $start, 2),
            'states' => $this->getRequest()->post['state'],
            'unaccreditedOnly' => $this->getRequest()->post['unaccreditedOnly'],
            'indicators' => $indicators
        ]));
    }

    /**
     * Generate a csv from the NESA Report screen
     * Recycled the same vars used to construct the report
     *
     * I can't trust the url to preserve the contents of the indicators chosen, which must be transmitted
     * in plaintext on the url (no id integers to rely upon)
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postNesaCsv()
    {
        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        $report = (new Report(
            $assessmentMapperProxy,
            $this->rubricMapper,
            $this->userMapper
        ))->generate(
            $this->getRequest()->post['state'],
            (int)$this->getRequest()->post['unaccreditedOnly'],
            $this->getRequest()->post['indicators']
        );

        /* There are dupes but we only match on text content
         */
        $indicators = array_filter(array_unique($this->getRequest()->post['indicators']));

        /* Render it out
         */
        $content = (new Csv())->generate($report, $indicators);
        $today = \Alaress_Date_Format::now()->getDatabaseDate();

        $headers = $this->getResponse()->headers;
        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', \strlen($content));
        $headers->set('Content-Disposition', "attachment; filename=\"nesa-{$today}.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }
}
