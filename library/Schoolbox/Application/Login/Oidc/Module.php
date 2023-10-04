<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\Login\Oidc;

use Alaress\Codec\Json\Base64;
use Alaress\Module\Module as BaseModule;
use Alaress\Redis\Cache\ItemPool;
use Alaress\Redis\Cache\KeyGenerator;
use Alaress\Web\Controller\Base;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;

/**
 * Module for OpenID Connect logins.
 */
class Module extends BaseModule
{
    protected $basename = 'loginOidc';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // Routes:
        // a GET/POST page for handling both creating an auth request, and
        // handling an auth response.
        // These routes should only exist if oAuth provider is set up.
        $this->register(self::METHOD_GET, '', 'getAuthorization');
        $this->register(self::METHOD_POST, '', 'postAuthorization');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $codec = new Base64();
        $cache = new SimpleCacheBridge(
            new ItemPool(
                $this->injector->get('redis.cache'),
                $codec,
                new KeyGenerator('oidc', INSTANCE_ID),
            )
        );
        return new Controller($this->injector->get('httpClient'), $codec, $cache);
    }
}
