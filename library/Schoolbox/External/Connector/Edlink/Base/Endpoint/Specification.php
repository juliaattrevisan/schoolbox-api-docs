<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * Explores Edlink endpoints to check whether they're accessible
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class Specification
{
    /**
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }

    /**
     * @return array
     */
    public function check(): array
    {
        $paths = [
            [
                'name' => 'People',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $this->api->retrieve('GET', 'people?$first=1');
                    } catch (\RuntimeException | \DomainException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
                    ];
                }
            ],
            [
                'name' => 'Agents (Parent/Guardian Relationships)',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $this->api->retrieve('GET', 'agents?$first=1');
                    } catch (\RuntimeException | \DomainException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
                    ];
                }
            ],
            [
                'name' => 'Schools',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $this->api->retrieve('GET', 'schools?$first=1');
                    } catch (\RuntimeException | \DomainException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
                    ];
                }
            ],
        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $output = $path['func']();

            if ($output['result']) {
                $endpoints[] = [
                    'message' => $path['name'],
                    'reason' => '',
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $path['name'],
                    'reason' => $output['errorMessage'],
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        return $endpoints;
    }
}
