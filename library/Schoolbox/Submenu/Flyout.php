<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Submenu;

use Schoolbox\IConfigLoader;

/**
 * Class Flyout
 *
 * An emulation of Admin Side Navigation that automatically generates the
 * hierarchy based on the Pastoral Types and SubTypes that currently exist
 *
 * @package Schoolbox\Submenu
 */
class Flyout
{

    /**
     * For access to booleans that determine availability/visibility of certain menu
     * items based on plan, configuration, etc.
     *
     * @var IConfigLoader
     */
    protected IConfigLoader $configLoader;

    /**
     * A set of (name, url) tuples for the first node(s) in a breadcrumb trail
     * All other nodes get appended to this stack
     *
     * e.g. this will setup the first two nodes in the breadcrumb trail
     *
     * $rootNode = [
     *     ['Node 1', $url1],
     *     ['Node 2', $url2]
     * ];
     *
     * @var array
     */
    protected array $rootNode;


    /**
     * Flyout constructor.
     *
     * @param IConfigLoader $configLoader
     * @param array         $rootNode
     */
    public function __construct(IConfigLoader $configLoader, array $rootNode)
    {
        $this->configLoader = $configLoader;

        /* The first node in the breadcrumbs
         */
        $this->rootNode = $rootNode;
    }


    /**
     * Generate breadcrumbs from the adminSubnav stack, for the consistency of names
     * in both the breadcrumbs themselves and also the submenu
     *
     * There are multiple implementations of breadcrumbs but there should be only one
     * Those should be removed in favour of this implementation
     *
     * @param string $url
     * @return array
     */
    public function generateBreadcrumbs(string $url): array
    {
        $allCrumbs = $this->rootNode;

        foreach ($this->generate() as $parentUrl => $parentData) {
            if ($parentUrl === $url) {
                $allCrumbs[] = [$parentData['name'], $parentUrl];
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        /* parentUrl is sometimes the same as a childUrl
                         * and manifest twice in the breadcrumb trail; we only
                         * need the parent node if it was different to its child node
                         */
                        if ($parentUrl !== $url) {
                            $allCrumbs[] = [$parentData['name'], $parentUrl];
                        }

                        $allCrumbs[] = [$childData['name'],  $childUrl];
                    }
                }
            }
        }

        return $allCrumbs;
    }


    /**
     * Decide the vars that you can pump into the specified template
     * so it can render the flyout with selected states
     *
     * @param string $url
     * @return array
     */
    public function generateVars(string $url): array
    {

        /* Look ahead for $url
         * If it's in a child node, remember its parent and pass that too
         */
        $pageCurrent = '';
        $subpageCurrent = '';

        foreach ($this->generate() as $parentUrl => $parentData) {
            if ($parentUrl === $url) {
                $pageCurrent = $parentUrl;
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        $pageCurrent = $parentUrl;
                        $subpageCurrent = $childUrl;
                    }
                }
            }
        }

        return [
            'nodes' => $this->generate(),
            'pageCurrent' => $pageCurrent,
            'subpageCurrent' => $subpageCurrent
        ];
    }


    /**
     * Generate a list of tuples that represent the Pastoral Type and Subtype hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * @return array
     */
    public function generate(): array
    {
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