<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\Callback;
use Alaress\Authorizer\Rule\IsConfigOn;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'mail';

    /**
     * @inheritDoc
     */
    public function registerRoutes(): void
    {
        $perm = new All(
            new IsLoggedIn(),
            new IsConfigOn(ConfigMapper::NAME_MAIL_ENABLED, $this->getConfig()),
            new Callback(static function (array $params, User $user) {
                if (!empty($user->getEmail())) {
                    return;
                }
                throw new Forbidden('Email is not set');
            })
        );
        $this->register(self::METHOD_GET, '/create', 'getCreateMail', $perm);
        $this->register(self::METHOD_POST, '/create', 'postCreateMail', $perm);
        $this->register(self::METHOD_POST, '/send', 'send', $perm);
        $this->register(self::METHOD_POST, '/feedback', 'postFeedback', new IsConfigOn(ConfigMapper::NAME_MAIL_ENABLED, $this->getConfig()));
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->injector->get('externalUser'),
            $this->injector->get('filesystem.storageProxy'),
            $this->injector->get('mail.emailFactory'),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
