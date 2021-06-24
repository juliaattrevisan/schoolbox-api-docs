<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Aura\Router\Router;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\SubType;

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
     * @var SubType\Mapper
     */
    private SubType\Mapper $subtypeMapper;


    /**
     * Flyout constructor.
     *
     * @param IConfigLoader  $configLoader
     * @param array          $rootNode
     * @param Router         $router
     * @param SubType\Mapper $subtypeMapper
     */
    public function __construct(
        IConfigLoader $configLoader,
        array $rootNode,
        Router $router,
        SubType\Mapper $subtypeMapper
    ) {
        parent::__construct($configLoader, $rootNode);

        /* Need aura to generate some routes for us
         */
        $this->router = $router;

        /* This is the source of our nodes
         */
        $this->subtypeMapper = $subtypeMapper;
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

        foreach ($this->subtypeMapper->findAll() as $subtype) {
            $typeUrl = $this->router->generate('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $subtype->getPastoralType()->getId()
            ]);

            $subtypeUrl = $this->router->generate('pastoralAudience.getAudiencesSubTypeModify', [
                'subtypeId' => $subtype->getId()
            ]);

            /* If the Type hasn't been seen yet, initialise it
             */
            if (!isset($stack[$typeUrl])) {
                $stack[$typeUrl] = [
                    'name' => $subtype->getPastoralType()->getName(),
                    'visible' => true,
                    'description' => $subtype->getPastoralType()->getName(),
                    'children' => []
                ];
            }

            /* Add the current SubType to the children of its parent Type
             */
            $stack[$typeUrl]['children'][$subtypeUrl] = [
                'name' => $subtype->getName(),
                'visible' => true,
                'description' => $subtype->getName(),
                'children' => []
            ];
        }

        return $stack;
    }
}