<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node;

/**
 * Controller for the curriculum usage API.
 */
class Controller extends Base
{
    public function __construct(private IConnector $database)
    {
    }

    /**
     * Endpoint for fetching a curriculum node's usage
     *
     * @api   GET /api/curriculum/usage/{id}
     * @since 23.0
     *
     * @param Node\Model $node
     * @return void
     */
    public function getNodeUsage(Node\Model $node): void
    {
        // Fetch usage:
        // write a cursor for fetching courses + units
        $encodedCursor = $this->request->query->get('cursor');
        $cursor        = new Cursor($this->database, $node, null);
        $data          = $cursor->retrieveResults($encodedCursor);

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $data,
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
