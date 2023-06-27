<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json\Decorator;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Api\Link;
use Alaress\Json\Linkable;
use Aura\Router\Generator;

/**
 * Decorates a JSON payload with links. The input model MUST implement \Alaress\Json\Linkable, or no decoration will
 * be attempted.
 *
 * @package Alaress\Json\Decorator
 */
class LinkDecorator implements IDecorator
{
    /** @var Generator */
    protected $routeGenerator;

    /** @var Authorizer */
    protected $auth;


    /**
     * @param Generator  $routeGenerator
     * @param Authorizer $auth
     */
    public function __construct(Generator $routeGenerator, Authorizer $auth)
    {
        $this->routeGenerator = $routeGenerator;
        $this->auth           = $auth;
    }


    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof Linkable) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        // iterate the links provided (if any) and render accordingly
        foreach ($model->getJsonApiLinks() as $linkName => $link) {
            $json['_links'][$linkName] = $this->parseLink($link);
        }

        return $json;
    }


    /**
     * Parse the given link object
     *
     * @param Link $link
     * @return array
     */
    protected function parseLink(Link $link): array
    {
        return [
            'href' => $link->getHref(),
            'auth' => $link->getAuth()
        ];
    }
}
