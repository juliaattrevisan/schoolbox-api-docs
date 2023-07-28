<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Web\Controller\Base;

/**
 * Controller for the search API.
 */
class Controller extends Base
{
    /**
     * Search for all results which match a keyword.
     *
     * @api GET /api/search
     * @since 23.1
     *
     * @return void
     */
    public function getSearch(): void
    {
        $this->response = $this->responseAsJson([]);
    }
}
