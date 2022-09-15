<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Find the actual human readable name of a subject identifier
 * (because burying it in another endpoint is convenient...)
 */
class Subject
{
    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * Constructor.
     *
     * @param IApi  $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Generate a list of subjects indexed by ImportIdentifiers
     */
    public function generate(string $year): array
    {
        $stack = [];

        $subjects = $this->api->retrieveDocument('schedule/v1/GetSubjects', [
            'year' => $year,
        ]);

        foreach ($subjects['d'] as $subject) {
            $stack[(string)$subject['importIdentifier']] = (string)$subject['longName'];
        }

        return $stack;
    }
}
