<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframe;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'iframe';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Staff members only
         */
        $isStaff = new Rule\HasRole(Role::STAFF);

        $this->register('GET', '/civica/attendance/{encodedUrl}', 'getCivicaAttendance', $isStaff);
        $this->register('GET', '/kami/interactive/{encodedUrl}', 'getKamiInteractiveView', $isStaff);

        //TODO to have more specific permission, possibly in persistent with the Paperly API call
        $isLoggedIn = new Rule\IsLoggedIn();

        $this->register('GET', '/paperly/{encodedUrl}/create_forms', 'getPaperlyCreateForms', $isLoggedIn);
        $this->register('GET', '/paperly/{encodedUrl}/create_excursions', 'getPaperlyCreateExcursion', $isLoggedIn);
        $this->register('GET', '/paperly/{encodedUrl}/view_pti', 'getPaperlyViewPti', $isLoggedIn);
        $this->register('GET', '/paperly/{encodedUrl}/complete_action', 'getPaperlyCompleteAction', $isLoggedIn);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory()->get(ErrorMapper::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
