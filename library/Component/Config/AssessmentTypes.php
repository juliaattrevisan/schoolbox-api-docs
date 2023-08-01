<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment;

class Component_Config_AssessmentTypes extends Component_Config_Abstract
{
    /**
     * Returns the options to display in the dropdown
     *
     * @return array<int, string>
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
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $di = $page->getDi();
        $ltiEnabled = $di->get('config')->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE);

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach ($this->getOptions($ltiEnabled) as $option => $value) {
            $page->setVar(array(
                              'COMPONENT_CONFIG_FILTER_VALUE'     => $option,
                              'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($option),
                              'COMPONENT_CONFIG_FILTER_SELECTED'  => $value == 1 ? 'checked="checked"' : '',
                          ));
            $page->parse('filters_block', 'filters', true);
        }
    }
}
