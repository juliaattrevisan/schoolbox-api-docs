<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node;

/**
 * Controller for the curriculum usage API.
 */
class Controller extends Base
{
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
    }
}
