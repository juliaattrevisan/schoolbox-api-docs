<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client\Api;

use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Oidc\Server\RelyingParty\Mapper;

/**
 * Module for setting up oAuth clients (a.k.a. OpenID Connect relying parties)
 * in Schoolbox's admin system.
 */
class Module extends BaseModule
{
    protected $basename = 'adminOauthClientApi';

    /**
     * Register routes for viewing, creating and updating relying parties.
     */
    protected function registerRoutes()
    {
        $permission = new IsSuperuser();
        $preload    = function (array $params) {
            return [
                'model' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
            ];
        };

        // API routes
        $this->register(self::METHOD_GET, '', 'getList', $permission);
        $this->register(self::METHOD_GET, '/{id:\\d+}', 'getItem', $permission, $preload);
        $this->register(self::METHOD_POST, '', 'postItem', $permission);
        $this->register(self::METHOD_PUT, '/{id:\\d+}', 'putItem', $permission, $preload);
        $this->register(self::METHOD_PATCH, '/{id:\\d+}', 'patchItem', $permission, $preload);
        $this->register(self::METHOD_DELETE, '/{id:\\d+}', 'deleteItem', $permission, $preload);
        $this->register(
            self::METHOD_POST,
            '/{id:\\d+}/regenerateSecret',
            'postRegenerateSecret',
            $permission,
            $preload,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Controller
    {
        return new Controller($this->getMapperFactory()->get(Mapper::class));
    }
}
