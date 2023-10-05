<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment;

class Component_Config_AssessmentTypes extends Component_Config_Abstract
{
    /**
     * @inheritdoc
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        return json_encode($value);
    }

    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null): void
    {
        $di = $page->getDi();
        $ltiEnabled = $di->get('config')->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE);

        // if config doesn't exist, then default to the get options...
        if ('' === $configValue) {
            $setConfig = Assessment\AssessmentType::getOptions($ltiEnabled);
        } else {
            $setConfig = json_decode(html_entity_decode($configValue), true);
            // in the event of an error return the blank entries ...
            if (null === $setConfig) {
                $setConfig = Assessment\AssessmentType::getOptions($ltiEnabled);
            }
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'assessmentfilter', 'assessmentfilter_block');
        $page->setBlock('assessmentfilter', 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach (Assessment\AssessmentType::getOptions($ltiEnabled) as $key => $filter) {
            $page->setVar([
                              'COMPONENT_CONFIG_FILTER_VALUE'     => $key,
                              'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($filter),
                              'COMPONENT_CONFIG_FILTER_SELECTED'  => $setConfig[$key] ?? 0 == 1 ? 'checked="checked"' : '',
                          ]);
            $page->parse('filters_block', 'filters', true);
        }
        $page->parse('assessmentfilter_block', 'assessmentfilter');
    }
}
