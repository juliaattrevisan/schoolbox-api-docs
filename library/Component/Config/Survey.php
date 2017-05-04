<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;

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

            $selectedValues   = [];
            $selectedValues[] = [
                'id'   => $survey->getId(),
                'name' => $survey->getFolder()->getName() . " - " . $survey->getTitle(),
            ];
            $page->setVar('COMPONENT_CONFIG_ID', $survey->getId());
            $page->setVar('COMPONENT_CONFIG_VALUE', json_encode($selectedValues));

        } catch (\Schoolbox\Mapper\Exception $e) {

            $page->setVar('COMPONENT_CONFIG_NAME', 'Survey');
            $page->setVar('COMPONENT_CONFIG_ID', '');
            $page->setVar('COMPONENT_CONFIG_VALUE', '[]');
        }
    }
}
