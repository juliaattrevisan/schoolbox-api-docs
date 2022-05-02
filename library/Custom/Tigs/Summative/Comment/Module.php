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
use Schoolbox\Role;

class Module extends Alaress\Module\Module
{
    /**
     * @var string
     */
    protected $basename = 'tigsSummativeComment';

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
     * @return Base
     */
    public function buildController(): Base
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
