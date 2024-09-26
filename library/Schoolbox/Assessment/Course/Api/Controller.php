<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Course\Api\Translator\ModelToArray;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;

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
    ) {
    }

    public function searchCourses(): void
    {
        $query = $this->requestQuery;

        $search = $query->get('query');
        $folder = $query->get('folder');
        $limit = $query->getInt('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $query->get('cursor');

        $cursor = new Cursor(
            $this->courseSpecificationMapper,
            $this->folderMapper,
            $this->unitSpecificationMapper,
            $this->access,
            $this->getUser(),
            $folder,
            $search,
            $limit
        );

        $data = $cursor->retrieveResults($encodedCursor);

        $folderTranslator = new Folder\Translator\ModelToArray\LinkOnly(99);
        $translator = new \Schoolbox\Learning\Course\DataModel\Specification\Translator\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $folderTranslator,
        );

        $courses = new Collection($data['courses'] ?? []);

        $result = $courses->map($translator);

        $this->response = $this->responseAsJson([
            'data' => $result,
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
