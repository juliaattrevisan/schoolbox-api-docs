<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Aura\Router\Router;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\Type;

/**
 * Class Flyout
 *
 * Render the Pastoral Types/SubTypes flyout menu
 *
 * @package Schoolbox\Pastoral\Audience
 */
class Flyout extends \Schoolbox\Submenu\Flyout
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
     * Flyout constructor.
     *
     * @param IConfigLoader $configLoader
     * @param array         $rootNode
     * @param Router        $router
     * @param Type\Mapper   $subtypeMapper
     */
    public function __construct(
        IConfigLoader $configLoader,
        array $rootNode,
        Router $router,
        Type\Mapper $subtypeMapper
    ) {
        parent::__construct($configLoader, $rootNode);

        /* Need aura to generate some routes for us
         */
        $this->router = $router;

        /* This is the source of our nodes
         */
        $this->typeMapper = $subtypeMapper;
    }


    /**
     * Generate a list of tuples that represent the Pastoral Type and Subtype hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function generate(): array
    {
        $stack = [];

        foreach ($this->typeMapper->findAll() as $type) {
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

            foreach ($type->getSubTypes() as $subtype) {
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
