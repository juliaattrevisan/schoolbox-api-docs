<?php
/**
 * help.schoolbox.com.au
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var CourseSpecificationMapper
     */
    private $courseSpecificationMapper;

    /**
     * @var CourseImplementationMapper
     */
    private $courseImplementationMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;


    /**
     * Controller constructor.
     * @param Error\Mapper               $errorMapper
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param Folder\Mapper              $folderMapper
     * @param Rubric\RubricMapper        $rubricMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        CourseSpecificationMapper $courseSpecificationMapper,
        CourseImplementationMapper $courseImplementationMapper,
        Folder\Mapper $folderMapper,
        Rubric\RubricMapper $rubricMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->folderMapper = $folderMapper;
        $this->rubricMapper = $rubricMapper;
    }


    /**
     * NESA Report rubric selection
     *
     * Selection of capabilities, indicators, descriptors which are used to configure a report output
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getNesa()
    {
        $courseSpecification = $this->courseSpecificationMapper->findById(Nesa\Acquisition::NESA_COURSE_SPEC_ID);

        /* Find all the folders that use this course implementation so we can chooze on the ui
         */
        $allFolders = new Collection();

        $allCourseImplementations = $this->courseImplementationMapper->findAllByCourseSpecificationId(Nesa\Acquisition::NESA_COURSE_SPEC_ID);

        foreach ($allCourseImplementations as $currentCourseImplementation) {
            $allFolders->add($currentCourseImplementation->getFolder());
        }

        $allFolders = $allFolders->sortBy('title', 'asc');

        $this->getResponse()->content->set($this->buildHtmlOutput('@help/selector.twig', [
            'course' => $courseSpecification,
            'allFolders' => $allFolders,
            'descriptors' => (new Nesa\Selector(Nesa\Acquisition::NESA_COURSE_SPEC_ID))->generate()
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
        try {
            $folder = $this->folderMapper->findById($this->getRequest()->post['folderId']);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_CATEGORY_DELETED);
        }

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* Acquire NESA data for $folder and the chosen indicators
         */
        $dataSet = (new Nesa\Acquisition(
            $this->getRequest()->post['indicators'],
            $this->rubricMapper,
            $assessmentMapperProxy
        ))->generate($folder->getId());

        /* Process the $dataSet into a visual report
         */
        $report = (new Nesa\Aggregation($dataSet))->generate();

        /* The usual output
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@help/report.twig', [
            'report' => $report,
            'folder' => $folder,
            'indicators' => array_filter(array_unique($this->getRequest()->post['indicators']))
        ]));
    }
}