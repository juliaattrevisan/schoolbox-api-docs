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
    protected function getOptions(bool $ltiEnabled): array
    {
        $types = [
            'dueWork'    => Assessment\DueWork\Model::getDefaultTypeName(),
            'quiz'       => Assessment\Quiz\Model::getDefaultTypeName(),
            'task'       => Assessment\Task\Model::getDefaultTypeName(),
            'project'    => Assessment\Project::getDefaultTypeName(),
            'lti'        => Assessment\Lti\Model::getDefaultTypeName(),
            'lessonPlan' => Assessment\LessonPlan\Model::getDefaultTypeName(),
        ];

        if (!$ltiEnabled) {
            unset($types['lti']);
        }
        return $types;
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
            $setConfig = $this->getOptions($ltiEnabled);
        } else {
            $setConfig = json_decode(html_entity_decode($configValue));
            // in the event of an error return the blank entries ...
            if (null === $setConfig) {
                $setConfig = $this->getOptions($ltiEnabled);
            }
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach ($setConfig as $option => $value) {
            $page->setVar(array(
                              'COMPONENT_CONFIG_FILTER_VALUE'     => $option,
                              'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($option),
                              'COMPONENT_CONFIG_FILTER_SELECTED'  => $value == 1 ? 'checked="checked"' : '',
                          ));
            $page->parse('filters_block', 'filters', true);
        }
    }
}
