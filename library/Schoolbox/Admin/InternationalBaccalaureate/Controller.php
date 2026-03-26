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
use Exception;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\GroupSubject\Mapper as IBGroupSubjectMapper;

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

        // Add General Configuration tab manually
        $categories = [
            [
                'id' => 0,
                'name' => 'general',
                'title' => 'General Configuration'
            ]
        ];

        // Fetch IB groups from database
        $groupMapper = new IBGroupMapper();
        $groupModels = $groupMapper->findByColumnList([]);

        $coreCategory = null;
        foreach ($groupModels as $groupModel) {
            $category = [
                'id' => $groupModel->getId(),
                'name' => strtolower(str_replace(' ', '-', $groupModel->getName())),
                'title' => $groupModel->getName()
            ];

            // Keep Core category separate to add at the end
            if ($groupModel->getName() === 'Core') {
                $coreCategory = $category;
            } else {
                $categories[] = $category;
            }
        }

        // Add Core category at the end
        if ($coreCategory) {
            $categories[] = $coreCategory;
        }

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

        if ($categoryId === 0) { // General tab - load from config table
            try {
                $configMapper = new ConfigMapper();

                // Get IB config entries that should exist from migration
                $schoolNameConfig = $configMapper->findOneByColumn(ConfigMapper::COL_NAME, 'ib_school_name');
                $schoolCodeConfig = $configMapper->findOneByColumn(ConfigMapper::COL_NAME, 'ib_school_code');

                $configurations = [];

                if ($schoolNameConfig !== null) {
                    $configurations[] = [
                        'id' => $schoolNameConfig->getId(),
                        'categoryId' => 0,
                        'key' => 'ib_school_name',
                        'value' => $schoolNameConfig->getValue() ?? '',
                        'description' => $schoolNameConfig->getDescription() ?? 'Enter the school\'s name as registered with the IB.',
                        'type' => 'text',
                        'label' => $schoolNameConfig->getTitle() ?? 'School Name'
                    ];
                }

                if ($schoolCodeConfig !== null) {
                    $configurations[] = [
                        'id' => $schoolCodeConfig->getId(),
                        'categoryId' => 0,
                        'key' => 'ib_school_code',
                        'value' => $schoolCodeConfig->getValue() ?? '',
                        'description' => $schoolCodeConfig->getDescription() ?? 'Enter the school\'s code as provided by the IB.',
                        'type' => 'text',
                        'label' => $schoolCodeConfig->getTitle() ?? 'School Code'
                    ];
                }

                // If no records found, return empty array (migration hasn't been run)
                if (empty($configurations)) {
                    $configurations = [];
                }
            } catch (Exception $e) {
                // If there's an error loading from config, return empty array
                $configurations = [];
            }
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
     * Get subjects for a specific category
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/subjects/{groupId}
     */
    public function getCategorySubjects(): void
    {
        // Get groupId from URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $groupId = 1; // Default
        if (preg_match('/\/subjects\/(\d+)/', $requestUri, $matches)) {
            $groupId = (int) $matches[1];
        }

        try {
            // Fetch subjects for this group from database
            $groupSubjectMapper = new IBGroupSubjectMapper();
            $groupSubjects = $groupSubjectMapper->findByGroupIdWithSubjects($groupId);

            $subjects = [];
            foreach ($groupSubjects as $groupSubject) {
                $subjects[] = [
                    'subject' => $groupSubject->subject_name,
                    'level' => $groupSubject->level ?: ''
                ];
            }

            header('Content-Type: application/json');
            echo json_encode($subjects);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Save configuration data
     *
     * @api POST /adminv2/international-baccalaureate/configuration/api/save
     */
    public function saveConfiguration(): void
    {
        try {
            // Get POST data
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            // Handle both single config and configurations array format
            if (!$data) {
                throw new Exception('Invalid request data');
            }

            $configMapper = new ConfigMapper();

            // Check if it's a single config object or an array of configurations
            if (isset($data['key']) && isset($data['value'])) {
                // Single configuration format from Vue component
                $configs = [$data];
            } elseif (isset($data['configurations'])) {
                // Array format (for future compatibility)
                $configs = $data['configurations'];
            } else {
                throw new Exception('Invalid request data format');
            }

            foreach ($configs as $config) {
                if (!isset($config['key']) || !isset($config['value'])) {
                    continue;
                }

                $configKey = $config['key'];
                $configValue = $config['value'];

                // Only update existing config records (from migration)
                try {
                    $existingConfig = $configMapper->findOneByColumn(ConfigMapper::COL_NAME, $configKey);

                    if ($existingConfig !== null) {
                        // Update existing config value
                        $existingConfig->setValue($configValue);
                        $configMapper->save($existingConfig);
                    } else {
                        // Config record doesn't exist - migration may not have been run
                        throw new Exception("Configuration '{$configKey}' not found. Please ensure the database migration has been run.");
                    }
                } catch (Exception $e) {
                    throw new Exception("Failed to save config '{$configKey}': " . $e->getMessage());
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved successfully'
            ]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ]);
            exit;
        }
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
