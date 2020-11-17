<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Cursor\MapperCursor;

/**
 * Controller for badge requests.
 */
class Controller extends Base
{
    /**
     * The badge mapper.
     */
    private Mapper $mapper;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    public function getBadges(): void
    {
        // Depending on request type:
        // this will fetch either a JSON or HTML result
        // The HTML result will only contain a Vue component,
        // the JSON will contain the actual results
        if ($this->isJsonRequest()) {
            // Also cursor this request
            // Add translators for this object too, write an API spec
            $cursor = new MapperCursor(
                $this->mapper,
                40,
                ['name' => $this->mapper::SORT_ORDER_ASC]
            );
            $results = $cursor->retrieveResults($this->request->query->get('cursor', null));
            // Run results thru the translator
            $this->response->content->set(
                $this->buildJsonOutput([
                    'data' => $results,
                    'metadata' => [
                        'count' => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'next'    => $cursor->next($results),
                            'current' => $cursor->current($results),
                        ]
                    ]
                ])
            );
            return;
        }

        // Output a Twig template
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/index.twig',
                [],
                'Badges'
            )
        );
    }
}
