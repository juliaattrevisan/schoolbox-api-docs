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
     *
     * @api GET /adminv2/international-baccalaureate/configuration
     */
    public function getConfiguration(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/configuration';
        
        // Define IB configuration categories (DP subject groups)
        $categories = [
            [
                'id' => 1,
                'name' => 'general',
                'title' => 'General Configuration'
            ],
            [
                'id' => 2,
                'name' => 'studies-language-literature',
                'title' => 'Studies in Language and Literature'
            ],
            [
                'id' => 3,
                'name' => 'language-acquisition',
                'title' => 'Language Acquisition'
            ],
            [
                'id' => 4,
                'name' => 'individuals-societies',
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
                'name' => 'arts',
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
     * Get configuration data for a specific category
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/{categoryId}
     */
    public function getConfigurationData(): void
    {
        // Get categoryId from URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $categoryId = 1; // Default
        if (preg_match('/\/configuration\/api\/(\d+)/', $requestUri, $matches)) {
            $categoryId = (int) $matches[1];
        }
        
        $configurations = [];
        
        if ($categoryId === 1) { // General tab - show actual form fields
            $configurations = [
                [
                    'id' => 1,
                    'categoryId' => 1,
                    'key' => 'school_name',
                    'value' => 'Example School',
                    'description' => 'Enter the school\'s name as registered with the IB.',
                    'type' => 'text',
                    'label' => 'School Name'
                ],
                [
                    'id' => 2,
                    'categoryId' => 1,
                    'key' => 'school_code',
                    'value' => 'ABC123',
                    'description' => 'Enter the school\'s code as provided by the IB.',
                    'type' => 'text',
                    'label' => 'School Code'
                ]
            ];
        } else {
            // All other tabs - return empty array for dummy content
            $configurations = [];
        }
        
        // Set proper headers
        header('Content-Type: application/json');
        echo json_encode($configurations);
        exit;
    }
    
    /**
     * Save configuration data
     *
     * @api POST /adminv2/international-baccalaureate/configuration/api/save
     */
    public function saveConfiguration(): void
    {
        // TODO: Validate and save to database
        // For now, just return success
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Configuration saved successfully'
        ]);
        exit;
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
