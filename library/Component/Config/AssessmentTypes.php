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
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null): void
    {
        $di = $page->getDi();
        $configLoader = $di->get('config');
        $ltiEnabled = $configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE);

        // TODO: avoid this AI config copypasta to check if dialog agent is enabled
        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $featureConfig = $aiConfig->decode((string)$configLoader->get(ConfigMapper::NAME_AI_DIALOGAGENT));
        $dialogAgentEnabled = $aiConfig->isEnabled($featureConfig);

        $assessmentTypeOptions = Assessment\AssessmentType::getOptions($ltiEnabled, $dialogAgentEnabled);

        // if config doesn't exist, then default to all options...
        $setConfig = $assessmentTypeOptions;
        if ($configValue !== '') {
            $decodedConfig = json_decode(html_entity_decode($configValue), true);
            // If the config is valid, use it instead of the default
            if ($decodedConfig !== null) {
                $setConfig = $decodedConfig;
            }
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'assessmenttypes', 'assessmenttypes_block');
        $page->setVar('assessmenttypes_block', '');

        /* filter config */
        foreach ($assessmentTypeOptions as $key => $filter) {
            $page->setVar([
                              'COMPONENT_CONFIG_FILTER_VALUE'     => $key,
                              'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($filter),
                              'COMPONENT_CONFIG_FILTER_SELECTED'  => $setConfig[$key] ?? 0 == 1 ? 'checked="checked"' : '',
                          ]);
            $page->parse('assessmenttypes_block', 'assessmenttypes', true);
        }
    }
}
