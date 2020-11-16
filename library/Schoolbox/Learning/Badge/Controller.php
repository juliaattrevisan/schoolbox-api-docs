<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\Web\Controller\Base;

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
            $this->response->content->set($this->buildJsonOutput([]));
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
