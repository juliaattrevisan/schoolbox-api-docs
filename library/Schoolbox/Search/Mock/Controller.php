<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Mock;

use Alaress\Web\Controller\Base as BaseController;
use Faker\Generator;

/**
 * The controller for the mock version of the search API.
 */
class Controller extends BaseController
{
    /**
     * @var ResultBuilder
     */
    private $resultBuilder;


    /**
     * Constructor
     *
     * @param ResultBuilder $resultBuilder
     */
    public function __construct(ResultBuilder $resultBuilder)
    {
        $this->resultBuilder = $resultBuilder;
    }


    /**
     * Respond with a mock search result.
     *
     * @api   GET /searchv2/
     * @since 19.2
     */
    public function getSearch(): void
    {
        $this->response->content->set(
            $this->buildJsonOutput(
                $this->resultBuilder->generateResultList(random_int(0, 10))
            )
        );
    }
}
