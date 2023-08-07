<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment;

class Component_Config_AssessmentTypes extends Component_Config_Abstract
{
    /**
     * Returns the list of assessment (activity) types to filter to.
     *
     * @return array<string, string>
     */
    protected function getOptions(): array
    {
        return [
            'dueWork'    => Assessment\AssessmentType::TYPE_DUE_WORK,
            'quiz'       => Assessment\AssessmentType::TYPE_QUIZ,
            'task'       => Assessment\AssessmentType::TYPE_TASK,
            'project'    => Assessment\AssessmentType::TYPE_PROJECT,
            'lti'        => Assessment\AssessmentType::TYPE_LTI_ASSESSMENT,
            'lessonPlan' => Assessment\AssessmentType::TYPE_LESSON_PLAN,
        ];
    }

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
            $setConfig = $this->getOptions();
        } else {
            $setConfig = json_decode(html_entity_decode($configValue), true);
            // in the event of an error return the blank entries ...
            if (null === $setConfig) {
                $setConfig = $this->getOptions();
            }
        }

        // if LTI is not enabled then remove that entry
        if (!$ltiEnabled) {
            unset($setConfig['lti']);
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach ($setConfig as $option => $value) {
            $page->setVar([
                              'COMPONENT_CONFIG_FILTER_VALUE'     => $option,
                              'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($option),
                              'COMPONENT_CONFIG_FILTER_SELECTED'  => $value == 1 ? 'checked="checked"' : '',
                          ]);
            $page->parse('filters_block', 'filters', true);
        }
    }
}
