<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;

class Component_Config_Survey extends Component_Config_Abstract
{
    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $surveyMapper = SurveyMapper::getInstance();

        $page->setVar('COMPONENT_INSTANCE_ID', $component->getInstance()->getId());

        try {

            /** @var Survey $survey */
            $survey = $surveyMapper->findById($configValue);

            $selectedValues = [];
            $selectedValues[] = [
                'id' => $survey->getId(),
                'name' => $survey->getFolder()->getName() . " - " . $survey->getTitle(),
            ];

            $page->setVar('COMPONENT_CONFIG_ID', $survey->getId());
            $page->setVar('COMPONENT_CONFIG_VALUE', json_encode($selectedValues));
        } catch (MapperException $e) {
            $page->setVar('COMPONENT_CONFIG_NAME', 'Survey');
            $page->setVar('COMPONENT_CONFIG_ID', '');
            $page->setVar('COMPONENT_CONFIG_VALUE', '[]');
        }
    }
}
