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
     * Constructor
     *
     * @param ISource $source
     */
    public function __construct(private ISource $source)
    {
    }

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
        // Build cursor as in
//        new Cursor(
//            $this->source,
//
//        );

        $keyword = (string)($this->requestQuery['keyword'] ?? '');
        if (mb_strlen($keyword) >= 2) {
            $this->response = $this->responseAsJson(
                $this->source->search(
                    $keyword,
                    new Filter(null, null, null, null),
                    null,
                    3,
                )
            );
        } else {
            $this->sendJsonError('Search keyword must be at least 2 characters', 400);
        }
    }
}
