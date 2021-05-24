<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Json\Decorator;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Api\Link;
use Aura\Router\Router;
use Schoolbox\Json\Api\AuraLink;
use Schoolbox\User;

/**
 * Schoolbox domain-specific extension of the Alaress JSON Link Decorator
 *
 * @package Schoolbox\Json\Decorator
 */
class LinkDecorator extends \Alaress\Json\Decorator\LinkDecorator
{
    /** @var null|User */
    private $user;


    /**
     * @param Router     $router
     * @param Authorizer $auth
     * @param null|User  $user
     */
    public function __construct(Router $router, Authorizer $auth, ?User $user)
    {
        parent::__construct($router, $auth);
        $this->user = $user;
    }


    /**
     * Parse the given link object
     *
     * @param Link $link
     * @return array
     */
    protected function parseLink(Link $link): array
    {
        if ($link instanceof AuraLink) {
            // don't like instanceof checks, but sometimes it's all you can do
            $link->configure($this->router, $this->auth, $this->user);
        }

        return parent::parseLink($link);
    }
}
