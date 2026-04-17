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
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\Group\Model as IBGroupModel;
use Schoolbox\IB\GroupSubject\Mapper as IBGroupSubjectMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Language\Model as LanguageModel;

class Controller extends ConfigController
{
    public function __construct(
        ConfigMapper $configMapper,
        private IBGroupMapper $groupMapper,
        private IBGroupSubjectMapper $groupSubjectMapper,
        private LanguageMapper $languageMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

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
                'title' => 'General Configuration',
            ],
        ];

        // Fetch IB groups from database
        $groupModels = $this->groupMapper->findAll();

        /** @var IBGroupModel $groupModel */
        foreach ($groupModels as $groupModel) {
            $categories[] = [
                'id' => $groupModel->getId(),
                'name' => strtolower(str_replace(' ', '-', $groupModel->getName())),
                'title' => $groupModel->getName(),
            ];
        }

        $languages = $this->languageMapper->findAll()->map(
            fn(LanguageModel $language) => [
                'id' => $language->getId(),
                'code' => $language->getCode(),
                'name' => $language->getName(),
            ]
        );

        $this->response = $this->responseFromTemplate(
            '@internationalBaccalaureate/configuration.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                'categories' => $categories,
                'languages' => $languages,
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

        if ($categoryId === 0) { // General tab - load from preloaded config
            $configLoader = $this->getConfigLoader();

            $configurations = [
                [
                    'categoryId' => 0,
                    'key' => ConfigMapper::NAME_IB_SCHOOL_NAME,
                    'value' => $configLoader->get(ConfigMapper::NAME_IB_SCHOOL_NAME) ?? '',
                    'description' => 'Enter the school\'s name as registered with the IB.',
                    'type' => 'text',
                    'label' => 'School Name',
                ],
                [
                    'categoryId' => 0,
                    'key' => ConfigMapper::NAME_IB_SCHOOL_CODE,
                    'value' => $configLoader->get(ConfigMapper::NAME_IB_SCHOOL_CODE) ?? '',
                    'description' => 'Enter the school\'s code as provided by the IB.',
                    'type' => 'text',
                    'label' => 'School Code',
                ],
            ];
        }

        $this->response = $this->responseAsJson($configurations);
    }

    /**
     * Get subjects for a specific category
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/subjects/{groupId}
     */
    public function getCategorySubjects(int $groupId): void
    {
        try {
            $groupSubjects = $this->groupSubjectMapper->findByColumn(
                'group_id',
                $groupId,
                [
                    'order' => ['s.name' => 'ASC', 'level' => 'ASC'],
                ]
            );

            $subjects = [];
            foreach ($groupSubjects as $groupSubject) {
                $subjects[] = [
                    'subject' => $groupSubject->getSubject()->getName(),
                    'level' => $groupSubject->getLevel() ?: '',
                ];
            }

            $this->response = $this->responseAsJson($subjects);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])->withStatus(500);
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
            $this->response = $this->responseAsJson(['success' => false, 'message' => 'Invalid request data'])->withStatus(400);
            return;
        }

        $this->updateConfig(
            [$data['key'] => $data['value']],
            $this->buildIbConfigPredicate()
        );

        $this->response = $this->responseAsJson(['success' => true, 'message' => 'Configuration saved successfully']);
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
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
            ]
        );
    }
}
