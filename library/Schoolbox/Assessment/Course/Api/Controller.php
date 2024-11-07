<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Api\Translator\ModelToArray;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseTranslator;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;
use ValueError;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     */
    public function __construct(
        private CourseSpecificationMapper $courseSpecificationMapper,
        private FolderMapper $folderMapper,
        private Access $access,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private Folder\Ancestry $ancestry,
    ) {
    }

    /**
     * Searching for courses depending on the search term
     *
     * @return void
     */
    public function searchCourses(): void
    {
        $query = $this->requestQuery;

        $search = $query->get('query');
        $folder = $query->get('folder');
        $limit = $query->getInt('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $query->get('cursor');

        //Early exit if limit is not of type int
        if (null === $limit) {
            $problem = (new ApiProblem("Limit must be an positive integer"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        try {
            $cursor = new Cursor(
                $this->courseSpecificationMapper,
                $this->folderMapper,
                $this->unitSpecificationMapper,
                $this->ancestry,
                $this->access,
                $this->getUser(),
                $folder,
                $search,
                $limit
            );

            $data = $cursor->retrieveResults($encodedCursor);
        } catch (\Exception | ValueError $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        /* Set the maximum parent lookup to 10 should be reasonable to reach all the
         * ancestors
         */
        $curriculumTranslator = new ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            0,
            0,
            null
        );
        $folderTranslator = new Folder\Translator\ModelToArray\ModelToArray(
            $curriculumTranslator,
            10
        );
        $translator = new CourseTranslator\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $folderTranslator,
        );

        $courses = new Collection($data['courses'] ?? []);
        $result = $courses->map($translator);

        $suggestions = $data['suggestions'] ?? new Collection();
        $suggests = $suggestions->map($translator);

        $this->response = $this->responseAsJson([
            'data' => [
                'courses' => $result,
                'suggestions' => $suggests,
            ],
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($courses),
                    'next' => (count($result) < $limit) ? null : $cursor->next($courses),
                ]
            ]
        ]);
    }
}
