<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\Comment;

use Alaress;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerInterface;
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
     * @param ContainerInterface $container
     * @return Base|null
     */
    public function buildController(ContainerInterface $container): ?Base
    {
        return new Controller();
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
