<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Aura\Router\Generator;
use Schoolbox\Curriculum\Node\Mapper;

/**
 * Curriculum tree filter component configuration.
 */
class Component_Config_CurriculumTreeFilter extends Component_Config_Abstract
{
    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {
        /** @var Generator $routeGenerator */
        $routeGenerator = $page->getDi()->get('router.generator');
        $page->setVar(
            'COMPONENT_CONFIG_CURRICULUMTREEFILTER_SEARCH_NODE_ENDPOINT',
            $routeGenerator->generate('curriculumApi.searchNode'),
        );

        // Load currently selected nodes into curriculum selector
        $mapper = Mapper::getInstance();
        $nodes  = $mapper->findByColumn('id', explode(',', $configValue))->reindex();
        $page->setVar(
            'COMPONENT_CONFIG_CURRICULUMTREEFILTER_SEARCH_SELECTED',
            Scrub::text(json_encode($nodes)),
        );
    }

    /**
     * `$value` should always be an array: reduce it to a comma-separated list.
     *
     * @param mixed                        $value
     * @param Component_ControllerAbstract $controller
     * @return mixed
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        return $value;
    }
}
