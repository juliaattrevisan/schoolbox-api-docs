<?php

class Component_Config_Range extends Component_Config_Abstract
{
    /**
     * Returns the options to display in the dropdown
     *
     * @return array<int, string>
     */
    protected function getOptions(): array
    {
        return [
            0 => 'Today Only',
            1 => '1 Day',
            2 => '2 Days',
            3 => '3 Days',
            4 => 'This week', // Week starting at config.calendar_start_day.  See CurrentLessonPlanController.php.
            5 => 'This month'
        ];
    }

    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {
        // has an internal block for the select element
        $page->setBlock($keyblock, 'range_opt', 'range_block');
        $page->setVar('range_block', '');

        foreach ($this->getOptions() as $key => $label) {
            $page->setVar('COMPONENT_CONFIG_RANGE_NAME', $label);
            $page->setVar('COMPONENT_CONFIG_RANGE_VALUE', $key);
            $page->setVar('COMPONENT_CONFIG_RANGE_SELECTED', (string)$key === $configValue ? ' selected="selected"' : '');
            $page->parse('range_block', 'range_opt', true);
        }
    }
}
