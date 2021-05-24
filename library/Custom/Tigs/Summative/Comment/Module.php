<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\Comment;

use Alaress;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;

class Module extends Alaress\Module\Module
{

    public const BASE_NAME = 'tigsSummativeComment';


    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, $basename ?: self::BASE_NAME);
    }


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isStaff = new Alaress\Authorizer\Rule\HasRole(Role::STAFF);

        $this->register('GET', '/role/{roleId}/term/{termSlug}', 'getDeanComments', $isStaff);
        $this->register('GET', '/folder/{folderId}/term/{termSlug}', 'getTeacherComments', $isStaff);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . self::BASE_NAME, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
