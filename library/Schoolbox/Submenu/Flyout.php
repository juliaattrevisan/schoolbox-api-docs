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
 * hierarchy based on the nodes provided
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
     * All the nodes in the flyout menu
     *
     * @var IFlyoutNodes
     */
    private IFlyoutNodes $nodes;


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
    protected array $rootNode = [];


    /**
     * Flyout constructor.
     *
     * @param IConfigLoader $configLoader
     * @param IFlyoutNodes $nodes
     */
    public function __construct(
        IConfigLoader $configLoader,
        IFlyoutNodes $nodes
    ) {
        $this->configLoader = $configLoader;

        /* All the nodes for the flyout menu
         */
        $this->nodes = $nodes;
    }


    /**
     * The first node(s) in the breadcrumbs
     *
     * You can optionally use this to prefix the breadcrumb trail if your flyout
     * is in some deep part of the system
     */
    public function prefixBreadcrumbs(array $rootNode): self
    {
        $this->rootNode = $rootNode;

        return $this;
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
            // The "searchable" key was added so that nodes could be excluded
            // from search results, because those nodes did not link anywhere.
            // Those nodes aren't searchable because they don't link anywhere
            // unique or useful, so don't generate breadcrumb links for those
            // nodes either.
            $parentSearchable = $parentData['searchable'] ?? true;
            if ($parentUrl === $url) {
                $allCrumbs[] = [$parentData['name'], $parentSearchable ? $parentUrl : null];
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        /* parentUrl is sometimes the same as a childUrl
                         * and manifest twice in the breadcrumb trail; we only
                         * need the parent node if it was different to its child node
                         */
                        if ($parentUrl !== $url) {
                            $allCrumbs[] = [$parentData['name'], $parentSearchable ? $parentUrl : null];
                        }

                        $childSearchable = $childData['searchable'] ?? true;
                        $allCrumbs[]     = [$childData['name'], $childSearchable ? $childUrl : null];
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

        $nodes = $this->generate();

        foreach ($nodes as $parentUrl => $parentData) {
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
            'nodes' => $nodes,
            'pageCurrent' => $pageCurrent,
            'subpageCurrent' => $subpageCurrent
        ];
    }


    /**
     * Generate a list of tuples that represent the nodes hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * @return array
     */
    public function generate(): array
    {
        return $this->nodes->generate();
    }
}
