<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    private const array CATEGORY_ORDER = [
        ConfigMapper::NAME_SKIN_CATEGORY_COLOURS,
        ConfigMapper::NAME_SKIN_CATEGORY_CSS,
    ];

    private const array SUBCATEGORY_ORDER = [
        ConfigMapper::NAME_SKIN_CATEGORY_COLOURS => [
            'general',
            'top menu',
            'side menu and notification',
            'user account',
            'component',
            'interactive',
            'form',
        ],
        ConfigMapper::NAME_SKIN_CATEGORY_CSS => [
            '',
        ],
    ];

    private const array OPTION_ORDER = [
        ConfigMapper::NAME_SKIN_CATEGORY_COLOURS => [
            'general' => [
                ConfigMapper::NAME_SKIN_COLOUR_BODY_BG,
                ConfigMapper::NAME_SKIN_COLOUR_LOGIN_BACKGROUND,
            ],
            'top menu' => [
                ConfigMapper::NAME_SKIN_COLOUR_NAVIGATION_BACKGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_NAVIGATION_FOREGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_NAVIGATION_HOVER,
            ],
            'side menu and notification' => [
                ConfigMapper::NAME_SKIN_COLOUR_OFFCANVAS_BACKGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_OFFCANVAS_FOREGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_OFFCANVAS_HOVER,
                ConfigMapper::NAME_SKIN_COLOUR_LOGO_BACKGROUND,
            ],
            'user account' => [
                ConfigMapper::NAME_ACCOUNTS_MENU_BG_COLOUR,
                ConfigMapper::NAME_ACCOUNTS_MENU_TEXT_COLOUR,
                ConfigMapper::NAME_ACCOUNTS_MENU_HOVER_COLOUR,
            ],
            'component' => [
                ConfigMapper::NAME_HOMEPAGE_COLOUR_HEAD_FONT,
                ConfigMapper::NAME_HOMEPAGE_COLOUR_HEAD_BG,
                ConfigMapper::NAME_HOMEPAGE_COLOUR_FONT,
                ConfigMapper::NAME_HOMEPAGE_COLOUR_BG_PRIMARY,
            ],
            'interactive' => [
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_FOREGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_BACKGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_HOVER,
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_SELECTED,
                ConfigMapper::NAME_SKIN_COLOUR_ACCENT_FOREGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_ACCENT_BACKGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_ACCENT_HOVER,
            ],
            'form' => [
                ConfigMapper::NAME_SKIN_COLOUR_FORM_INPUT_BORDER_COLOR,
                ConfigMapper::NAME_SKIN_COLOUR_FORM_BACKGROUND_HOVER_COLOR,
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_FOREGROUND,
                ConfigMapper::NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_BACKGROUND,
            ],
        ],
        ConfigMapper::NAME_SKIN_CATEGORY_CSS => [
            '' => [
                ConfigMapper::NAME_SKIN_CUSTOM_CSS,
            ],
        ],
    ];

    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
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
                'pageTitle' => 'Skin Config',
                'pageCurrent'    => $pageCurrent,
                'configs' => $configs,
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
        $skinConfigs = $this
            ->getConfigMapper()
            ->findByColumnList(
                [
                    'type_id' => ConfigMapper::TYPE_SKIN_CONFIG,
                    'hidden' => 'false',
                    'editable' => 'true',
                ]
            );

        /* Converting to JSON format
         */
        $data = $skinConfigs->reduce(function (array $carry, Config $config): array {
            $categoryName = self::normaliseName($config->getSkinSubcategory()->getCategory()->getName());
            $subcategoryId = $config->getSkinSubcategory()->getId();
            $subcategoryName = $config->getSkinSubcategory()->getName();

            // Create subcategory bucket once
            $carry[$categoryName][$subcategoryId] ??= [
                'name' => $subcategoryName,
                'options' => [],
            ];

            $carry[$categoryName][$subcategoryId]['options'][] = [
                'id' => $config->getId(),
                'name' => $config->getName(),
                'value' => $config->getValue(),
                'title' => $config->getTitle(),
                'description' => $config->getDescription(),
                'editable' => $config->getEditable(),
                'hidden' => $config->getHidden(),
            ];

            return $carry;
        }, []);

        // Convert subcategory maps to indexed arrays for JSON output
        foreach ($data as $category => $subcategoryMap) {
            uasort($subcategoryMap, function (array $left, array $right) use ($category): int {
                return $this->compareSubcategories($category, $left['name'] ?? null, $right['name'] ?? null);
            });

            foreach ($subcategoryMap as $subcategoryId => $subcategory) {
                usort($subcategory['options'], function (array $left, array $right) use ($category, $subcategory): int {
                    return $this->compareOptions(
                        $category,
                        $subcategory['name'] ?? null,
                        $left['name'],
                        $right['name']
                    );
                });

                $subcategoryMap[$subcategoryId] = $subcategory;
            }

            $data[$category] = array_values($subcategoryMap);
        }

        uksort($data, [$this, 'compareCategories']);

        return $data;
    }

    /**
     * Compare category keys using the intended Skin Config display order.
     */
    private function compareCategories(string $left, string $right): int
    {
        return $this->compareByList(self::CATEGORY_ORDER, $left, $right);
    }

    /**
     * Compare subcategory names within a category using the intended display order.
     */
    private function compareSubcategories(string $category, ?string $left, ?string $right): int
    {
        $order = self::SUBCATEGORY_ORDER[$category] ?? [];

        return $this->compareByList($order, self::normaliseName($left), self::normaliseName($right));
    }

    /**
     * Compare option config names within a category and subcategory using the intended display order.
     */
    private function compareOptions(string $category, ?string $subcategory, string $left, string $right): int
    {
        $order = self::OPTION_ORDER[$category][self::normaliseName($subcategory)] ?? [];

        return $this->compareByList($order, $left, $right);
    }

    /**
     * Compare two values against a preferred ordering list, falling back to natural string comparison.
     *
     * @param list<string> $order
     */
    private function compareByList(array $order, string $left, string $right): int
    {
        $leftIndex = array_search($left, $order, true);
        $rightIndex = array_search($right, $order, true);

        if ($leftIndex !== false && $rightIndex !== false) {
            return $leftIndex <=> $rightIndex;
        }

        if ($leftIndex !== false) {
            return -1;
        }

        if ($rightIndex !== false) {
            return 1;
        }

        return strnatcasecmp($left, $right);
    }

    /**
     * Normalise display names to stable lower-case keys for sorting.
     */
    private static function normaliseName(?string $name): string
    {
        return strtolower(trim((string)$name));
    }
}
