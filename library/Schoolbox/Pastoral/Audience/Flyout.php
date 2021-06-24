<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

class Flyout extends \Schoolbox\Submenu\Flyout
{

    /**
     * Generate a list of tuples that represent the Pastoral Type and Subtype hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * @return array
     */
    public function generate(): array
    {
        /* TODO Inject these later
         */
        $subtypeMapper = \Schoolbox\Pastoral\SubType\Mapper::getInstance();

        $nodes = $subtypeMapper->findAll();

        $stack = [];

        foreach ($nodes as $node) {

            /* If the Type hasn't been seen yet, initialise it
             */

            /* Add the current SubType to the children of its parent Type
             */
        }

        new \Alaress_Probe($nodes);
        return [
            'url_1' => [
                'name' => 'Node 1',
                'visible' => true,
                'description' => 'Description for node 1',
                'children' => [
                    'url_1_1' => [
                        'name' => 'Node 1.1',
                        'visible' => true,
                        'description' => 'Description for Node 1.1',
                        'children' => []
                    ],
                    'url_1_2' => [
                        'name' => 'Node 1.2',
                        'visible' => true,
                        'description' => 'Description for Node 1.2',
                        'children' => []
                    ],
                ]
            ],
            'url_2' => [
                'name' => 'Node 2',
                'visible' => true,
                'description' => 'Description for node 2',
                'children' => [
                    'url_1_1' => [
                        'name' => 'Node 2.1',
                        'visible' => true,
                        'description' => 'Description for Node 2.1',
                        'children' => []
                    ],
                ]
            ],
        ];
    }
}