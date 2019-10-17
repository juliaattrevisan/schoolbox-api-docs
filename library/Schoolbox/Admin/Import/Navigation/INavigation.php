<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Router;

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
     * @param Router $router
     * @return mixed
     */
    public function buildBreadcrumbs(Router $router);


    /**
     * Builds navigation for this page.
     *
     * @param Router $router
     * @param string $currentPage
     * @return mixed
     */
    public function buildNavigation(Router $router, string $currentPage = '');
}
