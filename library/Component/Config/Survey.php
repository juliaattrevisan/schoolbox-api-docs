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
        $folderMapper = FolderMapper::getInstance();
        $surveyMapper = SurveyMapper::getInstance();

        $page->setBlock($keyblock, 'surveys', 'surveys_block');
        $page->setVar('surveys_block', '');

        // Retrieve the logged in users identity

        /** @var User $user */
        $user = $page->getDi()->get('user');

        // Get a list of all the folders they have access to

        if ($user->isSuperuser()) {
            $folderIds = $folderMapper->findAll()->getColumnValues('id');
        } else {
            $folderIds = $folderMapper->findForUser($user, true)->getColumnValues('id');
        }

        // Find all the surveys on these pages

        $surveys = $surveyMapper->findByColumn('category_id', $folderIds);

        /** @var Survey $survey */
        foreach ($surveys as $survey) {
            $page->setVar('COMPONENT_CONFIG_NAME', $survey->getFolder()->getTitle() . ' - ' . $survey->getTitle());
            $page->setVar('COMPONENT_CONFIG_VALUE', $survey->getId());
            $page->setVar('COMPONENT_CONFIG_SELECTED', $survey->getId() == $configValue ? ' selected="selected"' : '');
            $page->parse('surveys_block', 'surveys', true);
        }
    }
}
