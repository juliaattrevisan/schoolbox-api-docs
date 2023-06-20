<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Generator;

/**
 * Interface INavigation
 *
 * Breadcrumb + Tab generators
 *
 * @package Schoolbox\Admin\Import\Navigation
 */
interface INavigation
{
    /**
     * Builds breadcrumbs for this page.
     *
     * @param Generator $router
     * @return mixed
     */
    public function buildBreadcrumbs(Generator $routeGenerator);


    /**
     * Builds navigation for this page.
     *
     * @param Generator $router
     * @param string    $currentPage
     * @return mixed
     */
    public function buildNavigation(Generator $routeGenerator, string $currentPage = '');
}
