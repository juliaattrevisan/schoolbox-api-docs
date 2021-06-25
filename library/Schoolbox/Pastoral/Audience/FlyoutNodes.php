<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Aura\Router\Router;
use Schoolbox\Pastoral\Type;
use Schoolbox\Submenu\IFlyoutNodes;

/**
 * Class FlyoutNodes
 *
 * Generate nodes for the Pastoral Audiencing flyout menu
 *
 * @package Schoolbox\Pastoral\Audience
 */
class FlyoutNodes implements IFlyoutNodes
{

    /**
     * @var Router
     */
    private Router $router;


    /**
     * @var Type\Mapper
     */
    private Type\Mapper $typeMapper;


    /**
     * FlyoutNodes constructor.
     *
     * @param Router        $router
     * @param Type\Mapper   $typeMapper
     */
    public function __construct(Router $router, Type\Mapper $typeMapper)
    {
        /* Need aura to generate some routes for us
         */
        $this->router = $router;

        /* This is the source of our nodes
         */
        $this->typeMapper = $typeMapper;
    }


    /**
     * Generate a list of tuples that represent the Pastoral Type and Subtype hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * See @inheritDoc for more details about the structure inside
     *
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function generate(): array
    {
        $stack = [];

        /* These aren't sorted by sequence
         */
        $types = $this->typeMapper->findAll()->sortBy('sequence', 'asc');

        foreach ($types as $type) {
            $typeUrl = $this->router->generate('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $type->getId()
            ]);

            /* If the Type hasn't been seen yet, initialise it
             */
            if (!isset($stack[$typeUrl])) {
                $stack[$typeUrl] = [
                    'name' => $type->getName(),
                    'visible' => true,
                    'description' => $type->getName(),
                    'children' => []
                ];
            }

            /* These aren't sorted by sequence
             */
            $subtypes = $type->getSubTypes()->sortBy('sequence', 'asc');

            foreach ($subtypes as $subtype) {
                $subtypeUrl = $this->router->generate('pastoralAudience.getAudiencesSubTypeModify', [
                    'subtypeId' => $subtype->getId()
                ]);

                /* Add the current SubType to the children of its parent Type
                 */
                $stack[$typeUrl]['children'][$subtypeUrl] = [
                    'name' => $subtype->getName(),
                    'visible' => true,
                    'description' => $subtype->getName(),
                    'children' => []
                ];
            }
        }

        return $stack;
    }
}
