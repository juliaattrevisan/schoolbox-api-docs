<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Submenu;

/**
 * Interface IFlyoutNodes
 *
 * The Flyout menu accepts payloads that fulfil this interface
 *
 * @package Schoolbox\Submenu
 */
interface IFlyoutNodes
{

    /**
     * Generate an array of nodes to use in the Flyout menu
     * Example usage as follows. It's only loosely defined but
     * you could harden it with an IFlyoutNode implementation
     * instead of a loose array struct
     *
     *    return [
     *        'url_1' => [
     *            'name' => 'Node 1',
     *            'visible' => true,
     *            'description' => 'Description for node 1',
     *            // false to make this node not appear in searches:
     *            // true if not provided
     *            'searchable' => true,
     *            // false to make this node not have a link in a breadcrumb:
     *            // true if not provided
     *            'hasBreadcrumbLink' => true,
     *            'children' => [
     *                'url_1_1' => [
     *                    'name' => 'Node 1.1',
     *                    'visible' => true,
     *                    'description' => 'Description for Node 1.1',
     *                    'children' => []
     *                ],
     *                'url_1_2' => [
     *                    'name' => 'Node 1.2',
     *                    'visible' => true,
     *                    'description' => 'Description for Node 1.2',
     *                    'children' => []
     *                ],
     *            ]
     *        ],
     *        'url_2' => [
     *            'name' => 'Node 2',
     *            'visible' => true,
     *            'description' => 'Description for node 2',
     *            'children' => [
     *                'url_1_1' => [
     *                    'name' => 'Node 2.1',
     *                    'visible' => true,
     *                    'description' => 'Description for Node 2.1',
     *                    'children' => []
     *                ],
     *            ]
     *        ],
     *    ];
     *
     * @return array
     */
    public function generate(): array;
}
