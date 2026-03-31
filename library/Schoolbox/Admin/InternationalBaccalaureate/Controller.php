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

use Alaress\Database\Predicate;
use Alaress\Web\Controller\ConfigController;
use Exception;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\GroupSubject\Mapper as IBGroupSubjectMapper;

class Controller extends ConfigController
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
    public function getConfigurationData(int $categoryId): void
    {
        $configurations = [];

        if ($categoryId === 0) { // General tab - load from config table
            try {
                $configMapper = $this->getConfigMapper();

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
    public function getCategorySubjects(int $groupId): void
    {
        try {
            $groupSubjectMapper = new IBGroupSubjectMapper();
            $groupSubjects = $groupSubjectMapper->findByGroupId($groupId);

            $subjects = [];
            foreach ($groupSubjects as $groupSubject) {
                $subjects[] = [
                    'subject' => $groupSubject->getSubject()->getName(),
                    'level' => $groupSubject->getLevel() ?: ''
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
        $data = (array) $this->requestPost;

        if (!isset($data['key']) || !isset($data['value'])) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit;
        }

        $this->updateConfig(
            [$data['key'] => $data['value']],
            $this->buildIbConfigPredicate()
        );

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Configuration saved successfully']);
        exit;
    }

    private function buildIbConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_IB);
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
