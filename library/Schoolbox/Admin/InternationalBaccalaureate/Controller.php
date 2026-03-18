<?php

/**
 * International Baccalaureate admin controller
 *
 * @author      Haris Ali <haris.ali@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends BaseController
{
    /**
     * DP Subject Configuration page
     * Shows empty tabs - full implementation moved to feature-develop/SA-21578
     *
     * @api GET /adminv2/international-baccalaureate/configuration
     */
    public function getConfiguration(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/configuration';
        
        // Define IB configuration categories (empty tabs for now)
        $categories = [
            [
                'id' => 1,
                'name' => 'general',
                'title' => 'General Configuration'
            ],
            [
                'id' => 2,
                'name' => 'studies-in-language-and-literature',
                'title' => 'Studies in Language and Literature'
            ],
            [
                'id' => 3,
                'name' => 'language-acquisition',
                'title' => 'Language Acquisition'
            ],
            [
                'id' => 4,
                'name' => 'individuals-and-societies',
                'title' => 'Individuals and Societies'
            ],
            [
                'id' => 5,
                'name' => 'sciences',
                'title' => 'Sciences'
            ],
            [
                'id' => 6,
                'name' => 'mathematics',
                'title' => 'Mathematics'
            ],
            [
                'id' => 7,
                'name' => 'the-arts',
                'title' => 'The Arts'
            ],
            [
                'id' => 8,
                'name' => 'core',
                'title' => 'Core'
            ]
        ];
        
        $this->response = $this->responseFromTemplate(
            '@internationalBaccalaureate/configuration.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                'categories' => $categories
            ]
        );
    }

    /**
     * Registration Reports page
     *
     * @api GET /adminv2/international-baccalaureate/registrations
     */
    public function getRegistrations(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/registrations';
        $this->response = $this->responseDependentOnRequest(
            '@internationalBaccalaureate/registrations.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent)
            ]
        );
    }
}
