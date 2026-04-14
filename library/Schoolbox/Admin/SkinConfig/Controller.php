<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig;

use Alaress\Database\Predicate;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Request\Values;
use Alaress\Web\Controller\ConfigController;
use Crell\ApiProblem\ApiProblem;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Field\Mapper as FieldMapper;
use Schoolbox\GenericCollection;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class Controller extends ConfigController
{
    public function __construct(
        ConfigMapper $configMapper,
        readonly private FieldMapper $fieldMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private Environment $twig,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * Handles the GET request to display the skin configuration page in the admin interface.
     *
     * This method retrieves the current skin configuration options, organizes them by category and subcategory,
     * and renders the appropriate Twig template with the necessary data for display.
     */
    public function getConfig(): void
    {
        $pageCurrent = $this->routeGenerator->generate('adminDesignSkinConfig.getConfig');

        $configs = $this->viewConfig();

        $this->response = $this->responseFromTemplate(
            '@adminDesignSkinConfig/index.twig',
            [
                'pageTitle'      => 'Skin Config',
                'pageCurrent'    => $pageCurrent,
                'configs'        => $configs,
                'allBreadcrumbs' => (new Subnav(
                    $this->getConfigLoader(),
                    $this->getUser()
                ))->generateBreadcrumbs($this->routeGenerator->generate('adminDesignSkinConfig.getConfig')),
            ]
        );
    }

    /**
     * Retrieves skin configuration options, grouped by category and subcategory, for rendering in the admin interface.
     *
     * @return array An associative array structured as [category][subcategory][] of config options.
     */
    private function viewConfig(): array
    {
        $fieldMap = $this->fieldMapper->findAll()->indexBy('id');

        $skinConfigs = $this
            ->getConfigMapper()
            ->findByColumnList(
                [
                    'type_id'  => [
                        ConfigMapper::TYPE_SKIN_CONFIG,
                        ConfigMapper::TYPE_SONAR_SKIN_CONFIG,
                    ],
                    'editable' => 'true',
                ],
                [
                    'order' => [
                        'scc.id'             => 'ASC',
                        'scs.id'             => 'ASC',
                        'sequence'           => 'ASC',
                        ConfigMapper::COL_ID => 'ASC',
                    ],
                ]
            );

        /* Converting to JSON format
         */
        $data = $skinConfigs->reduce(function (array $carry, Config $config) use ($fieldMap): array {
            if ($config->getName() === ConfigMapper::NAME_SONAR_UI_TOGGLE) {
                // Skip the toggle config as it's handled separately
                $carry['toggle'] = [
                    'enabled'     => (bool)$config->getValue(),
                    'title'       => $config->getTitle(),
                    'description' => $config->getDescription(),
                ];
            } else {
                $categoryName  = $config->getSkinSubcategory()->getCategory()->getName();
                $subcategoryId = $config->getSkinSubcategory()->getId();
                $type          = $config->getType()->getId() === ConfigMapper::TYPE_SONAR_SKIN_CONFIG ? 'sonar' : 'existing';

                // Create subcategory bucket once
                $carry[$type][$categoryName][$subcategoryId] ??= [
                    'name'    => $config->getSkinSubcategory()->getName(),
                    'options' => [],
                ];

                $carry[$type][$categoryName][$subcategoryId]['options'][] = [
                    'id'             => $config->getId(),
                    'name'           => $config->getName(),
                    'value'          => $config->getValue(),
                    'title'          => $config->getTitle(),
                    'description'    => $config->getDescription(),
                    'customProperty' => $this->getCustomProperty($config),
                    'type'           => $this->getOptionType($config, $fieldMap),
                    'editable'       => $config->getEditable(),
                    'hidden'         => $config->getHidden(),
                ];
            }

            return $carry;
        }, []);

        // Convert subcategory maps to indexed arrays for JSON output
        foreach ($data as $type => $categories) {
            foreach ($categories as $category => $subcategoryMap) {
                if (is_array($subcategoryMap)) {
                    $data[$type][$category] = array_values($subcategoryMap);
                }
            }
        }

        return $data;
    }

    /**
     * Persist the submitted Skin Config payload.
     */
    public function postConfig(): void
    {
        $postedConfigs = $this->requestPost->get('configs') ?? [];
        $database = $this->getConfigMapper()->getDatabase();

        try {
            $database->beginTransaction();

            $toggleEnabled = $this->extractPostedToggleEnabled($postedConfigs);
            if ($toggleEnabled !== null) {
                $this->persistSonarToggleConfig($toggleEnabled);
            }

            $this->persistPostedConfigs($this->flattenPostedConfigs($postedConfigs));

            $database->commit();
            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $database->rollBack();
            $status  = Response::HTTP_INTERNAL_SERVER_ERROR;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        }
    }

    /**
     * Build a temporary preview stylesheet from the unsaved Skin Config payload.
     */
    public function previewCss(): void
    {
        try {
            $previewData  = (new \Schoolbox\Admin\SkinConfig\Helper($this->getConfigMapper(), $this->getConfigLoader()))->buildPreviewData($this->requestPost->get('configs') ?? []);
            $css          = $this->twig->render('css/skin.twig', $previewData);
            $cacheControl = 'no-store, no-cache, must-revalidate, max-age=0';

            $this->response = $this->responseFromContent($css, null, 'text/css')->withHeader('Cache-Control', $cacheControl);
        } catch (\Throwable $e) {
            $status  = Response::HTTP_INTERNAL_SERVER_ERROR;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        }
    }

    /**
     * Map the config field type to the frontend Skin Config input type.
     *
     * @param Config            $config
     * @param GenericCollection $fieldMap
     * @return string|null
     */
    private function getOptionType(Config $config, GenericCollection $fieldMap): ?string
    {
        return $fieldMap->offsetGet($config->getFieldId())?->getField();
    }

    /**
     * Extract the raw CSS custom property mapping from config options metadata.
     */
    private function getCustomProperty(Config $config): ?string
    {
        $options = $config->getOptions();
        if ($options === null || trim($options) === '') {
            return null;
        }
        $decoded = json_decode($options, true);
        if (!is_array($decoded)) {
            return null;
        }
        $customProperty = $decoded['customProperty'] ?? null;
        return is_string($customProperty) && $customProperty !== '' ? $customProperty : null;
    }

    /**
     * Returns a predicate builder for editable, visible Skin Config rows.
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->in('type_id', [
                        ConfigMapper::TYPE_SKIN_CONFIG,
                        ConfigMapper::TYPE_SONAR_SKIN_CONFIG,
                    ])
                    ->equals('hidden', 'false')
                    ->equals('editable', 'true');
    }

    /**
     * Extract the posted Sonar toggle value and normalise it to the persisted config string.
     *
     * @param array<array-key, mixed> $configs
     */
    private function extractPostedToggleEnabled(array $configs): ?string
    {
        $toggle = $configs['toggle'] ?? null;
        if (!is_array($toggle)) {
            return null;
        }

        $enabled = (new Values($toggle))->getBool('enabled');
        if ($enabled === null) {
            return null;
        }

        return $enabled ? '1' : '0';
    }

    /**
     * Persist the Advanced UI feature toggle as a separate named config row.
     */
    private function persistSonarToggleConfig(string $enabled): void
    {
        $toggleConfig = $this->getConfigMapper()->findOneByColumn('name', ConfigMapper::NAME_SONAR_UI_TOGGLE);
        if ($toggleConfig->getValue() === $enabled) {
            return;
        }

        $this->getConfigMapper()->save($toggleConfig->setValue($enabled));
    }

    /**
     * Validate and persist the posted Skin Config name/value payload.
     *
     * Uses the standard field serializers, but saves via constrained SQL updates instead of ConfigMapper::save()
     * so Skin Config join metadata is not re-persisted during save.
     *
     * @param array<int, string|null> $postedConfigs
     */
    private function persistPostedConfigs(array $postedConfigs): void
    {
        $database        = $this->getConfigMapper()->getDatabase();
        $existingConfigs = $this->getConfigMapper()->findByPredicates($this->buildConfigPredicate())->indexBy('id');

        foreach ($postedConfigs as $configId => $value) {
            if (!$existingConfigs->keyExists($configId)) {
                continue;
            }

            /** @var Config $existingConfig */
            $existingConfig  = $existingConfigs[$configId];
            $valueSerializer = $this->getFieldStrategyFactory()->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value, $existingConfig);

            if ($newValue === $existingConfig->getValue()) {
                continue;
            }

            $database->prepareQuery(
                'UPDATE config
                SET value = :value
                WHERE configID = :configID',
                [
                    'value'    => $newValue,
                    'configID' => $existingConfig->getId(),
                ]
            )->execute();
        }
    }

    /**
     * Flatten the posted nested Skin Config payload to a name/value map.
     *
     * @param array<array-key, mixed> $configs
     * @return array<int, string|null>
     */
    private function flattenPostedConfigs(array $configs): array
    {
        $flattenedConfigs = [];
        foreach ($configs as $type => $categories) {
            if ($type === 'toggle') {
                continue;
            }

            if (!is_array($categories)) {
                continue;
            }
            foreach ($categories as $subcategories) {
                if (!is_array($subcategories)) {
                    continue;
                }
                foreach ($subcategories as $subcategory) {
                    if (!is_array($subcategory) || !isset($subcategory['options']) || !is_array($subcategory['options'])) {
                        continue;
                    }
                    foreach ($subcategory['options'] as $option) {
                        if (!is_array($option) || !isset($option['id'])) {
                            continue;
                        }

                        $optionId = (new Values($option))->getInt('id');
                        if ($optionId === null) {
                            continue;
                        }

                        $flattenedConfigs[$optionId] = isset($option['value']) && is_string($option['value']) ? $option['value'] : null;
                    }
                }
            }
        }
        return $flattenedConfigs;
    }
}
