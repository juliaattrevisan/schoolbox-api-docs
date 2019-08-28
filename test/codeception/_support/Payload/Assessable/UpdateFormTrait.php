<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Assessable;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Project;
use UnexpectedValueException;

/**
 * Fills the assessable modify form with an assessable's attributes
 */
trait UpdateFormTrait
{
    use FormTrait;

    /**
     * @param IAssessable $assessable
     * @param string      $returnUrl
     * @throws UnexpectedValueException
     */
    private function populateForm(IAssessable $assessable, string $returnUrl): void
    {
        $this->setFormParam('assessmentType', AssessmentType::objectToString($assessable));
        $this->setFormParam('id', $assessable->getId());
        $this->setFormParam('returnUrl', $returnUrl);
        $this->setFormParam('folderId', $assessable->getBaseContext()->getFolder()->getId());
        $this->setTitle($assessable->getTitle());
        $this->setFormParam('workType', $assessable->getWorkType() ? $assessable->getWorkType()->getId() : '');
        $this->setFormParam('description', $assessable->getDescription());
        if ($assessable instanceof DueWork\Model) {
            $this->setFormParam('acceptOnline', $assessable->getAcceptOnline());
        }
        $this->setFormParam('publishDate', $assessable->getPublishAt()->getUsingFormat(Alaress_Date_Format::FORMAT_DATE_PICKER));
        $this->setFormParam('dueDate', $assessable->getDueAt()->getUsingFormat(Alaress_Date_Format::FORMAT_DATE_PICKER));
        $this->setFormParam('openDate', $assessable->getOpenAt()->getUsingFormat(Alaress_Date_Format::FORMAT_DATE_PICKER));
        $this->setFormParam('closeDate', $assessable->getCloseAt()->getUsingFormat(Alaress_Date_Format::FORMAT_DATE_PICKER));
        if ($assessable instanceof Project) {
            $this->setFormParam('summator', $assessable->getSummator()::getTitle());
        }
        $this->setFormParam('weight', $assessable->getWeight());
        if ($assessable instanceof DueWork\Model) {
            $this->setFormParam('self_reviewable', $assessable->getSelfReviewability());
        }
        $this->setFormParam('visibility', $assessable->getMarkVisibility());
        $this->setFormParam('markType', $assessable->getMarkType() ? $assessable->getMarkType()->getId() : '');
        $this->setFormParam('maxMark', $assessable->getMaxMark());
        $this->setFormParam('gradingScheme', $assessable->getGradeGroup() ? $assessable->getGradeGroup()->getId() : '');
        $this->setFormParam('resultSet', 1);

        foreach ($assessable->getPlans() as $plan) {
            foreach ($plan->getParticipants() as $participant) {
                $this->setFormParam("participant-include[{$participant->getUser()->getId()}]", 1);
                $this->setFormParam("participant-plan[{$participant->getUser()->getId()}]", $plan->getIsDynamic() ? 0 : $plan->getId());
            }
        }

//        $this->setFormParam('curriculumCodes', ''); // TODO
    }
}
