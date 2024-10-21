<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Curriculum\Api\Translator\ModelToArray;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseTranslator;
use Schoolbox\Resource\Folder;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param CurriculumMapper  $curriculumMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CurriculumMapper $curriculumMapper
    ) {
    }


    /**
     * Get the coverage report
     */
    public function getCoverage(): void
    {
        /**
         * temporary code to get the course object
         */
        if (isset($_GET['course-id'])) {
            $courseId =  $_GET['course-id'];
            $CM = \Schoolbox\Learning\Course\DataModel\Specification\Mapper::getInstance();
            $courses = new Collection([$CM->findById($courseId)]);
            $folderTranslator = new Folder\Translator\ModelToArray\LinkOnly(
                10
            );
            $translator = new CourseTranslator\ModelToArray(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
                $folderTranslator,
            );
            $selectedCourses = [];
            $result = $courses->map($translator);
            foreach ($result as $resultItem) {
                array_push($selectedCourses, ["id" => $resultItem['id'], "name" => $resultItem['title']]);
            }
        } else {
            $selectedCourses = [];
        }

        $this->response = $this->responseFromTemplate('@curriculumreports/index.twig', [
            'var' => 'GET',
            'error' => $this->errorMapper,
            'curric' => $this->curriculumMapper,
            'selectedCourses' => $selectedCourses,
        ]);
    }


    /**
     * fetch the coverage report
     */
    public function postCoverage(): void
    {
        $this->response = $this->responseFromTemplate('@curriculumreports/index.twig', ['var' => 'POST']);
    }
}
