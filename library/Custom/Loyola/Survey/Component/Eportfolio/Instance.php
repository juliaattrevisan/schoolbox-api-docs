<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Loyola\Survey\Component\Eportfolio;

use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;

class Instance extends Component_Instance
{
    /** @var SurveyMapper $surveyMapper */
    protected $surveyMapper;

    /** @var Survey $survey */
    protected $survey;

    /**
     * {@inheritDoc}
     *
     * Hide the component from non-staff if the survey has not been configured
     *
     * @param string $context The component context we are in (homepage, eportfolio, dashboard)
     * @param int $contextId The context ID we are current in (folderId, pageId, dashboardId)
     * @return bool
     */
    public function renderInPalette($context, $contextId)
    {
        /** @var User $user */
        $user = $_SESSION["user"];

        // Try to load the survey we are going to render

        $this->surveyMapper = SurveyMapper::getInstance();

        try {
            $this->survey = $this->surveyMapper->findById($this->getConfigByKey('survey')->getValue());
        } catch (MapperException $e) {
            // No survey was set in the components template instance (or the survey no longer exists)

            // Only staff can interact with the component if there is no survey set- so if the logged in
            // user is not a staff member- they should not see this component

            if (!$user->getRole()->isStaff()) {
                return false;
            }
        }

        return true;
    }
}
