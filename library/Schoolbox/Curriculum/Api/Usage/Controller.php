<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;

/**
 * Controller for the curriculum usage API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param IConnector $database Database required for building cursor object
     */
    public function __construct(
        private IConnector $database,
        private Folder\Mapper $folderMapper,
        private CourseSpec\Mapper $courseSpecMapper,
        private UnitSpec\Mapper $unitSpecMapper,
    ) {
    }

    /**
     * Endpoint for fetching a curriculum node's usage.
     *
     * @api   GET /api/curriculum/usage/{id}
     * @since 23.0
     *
     * @param Node\Model $node
     * @return void
     */
    public function getNodeUsage(Node\Model $node): void
    {
        // Fetch usage
        $encodedCursor = $this->request->query->get('cursor');
        $limit         = $this->request->query->getInt('limit', Cursor::LIMIT_DEFAULT);
        $cursor        = new Cursor($this->database, $node, $limit, null);
        $data          = $cursor->retrieveResults($encodedCursor);

        $counter = new Counter($this->database);
        $counts  = $counter->count([$node->getId()]);

        // I can't create this in the module because a user object is required,
        // and the user doesn't reliably exist in the module.
        $hydrator = new Hydrator(
            $this->folderMapper,
            new Folder\Translator\ModelToArray\LinkOnly(),
            $this->courseSpecMapper,
            new CourseSpec\Translator\ModelToArray($this->router, $this->authorizer, $this->getUser()),
            $this->unitSpecMapper,
            new UnitSpec\Translator\ModelToArray($this->router, $this->authorizer, $this->getUser()),
        );

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $hydrator->hydrateResults($data),
                'metadata' => [
                    'count' => $cursor->retrieveResultCount(),
                    'cursor' => [
                        'current' => $encodedCursor ?? $cursor->current($data),
                        'next'    => $cursor->next($data),
                    ],
                ],
            ]),
        );
    }
}
