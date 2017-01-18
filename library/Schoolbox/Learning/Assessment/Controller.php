<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Aura\Web\Request;
use DomainException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use \Alaress_Date_Format;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;

abstract class Controller extends ListController implements IAssessmentController
{

    /** @var BreadcrumbFactory */
    protected $bakery;

    /**
     * Controller constructor.
     *
     * @param UserMapper            $userMapper
     * @param ErrorMapper           $errorMapper
     * @param DataModel\MapperProxy $assessmentMapper
     * @param Access                $access
     * @param Transactor            $transactor
     * @param BreadcrumbFactory     $bakery
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor);
        $this->bakery = $bakery;
    }

    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @level 165
     *
     * @param AssessmentContext $context
     */
    public function getCreateAssessmentForm(AssessmentContext $context)
    {
        $type = $this->getRequest()->query->get('type');

        // build a stub
        $assessment = AssessmentType::stringToObject($type, $context);

        $data = $this->getAssessmentAsTwigArray($context, $assessment);

        /* Curriculum Codes prefilled from those associated with context folder
         */
        $data['curriculumNodes'] = $context->getFolder()->getCurriculumNodes();

        // Did the caller request a returnUrl?
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        // either output the fragment to the mage, or use the synchronous form
        if ($this->getRequest()->isXhr()) {
            $content = $this->buildUnthemedHtmlOutput("@learningAssessment/fragments/$type/create.twig", $data);

        } else {
            $content = $this->buildHtmlOutput('@learningAssessment/create.twig', $data);
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * Get the form to update an existing Assessment's details
     *
     * @api /learning/assessments/:assessmentId/modify
     * @level 165
     * @param IAssessable $assessment
     */
    public function getUpdateAssessmentForm(IAssessable $assessment)
    {
        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);
        $data['breadcrumbs'] = $this->getBakery()->produceFromAssessment($assessment,$this->getUser())->add([$assessment->getTitle()]);
        $data['returnUrlName'] = Redirect::RETURN_URL;
        $data['returnUrl'] = $this->getRequest()->query->get(Redirect::RETURN_URL);

        /* Curriculum Codes prefilled from those associated with the assessment itself
         */
        $data['curriculumNodes'] = $assessment->getCurriculumNodes();

        $template = "@learningAssessment/update.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @api   POST /learning/assessments/:assessmentId
     * @level 165
     *
     * @param IAssessable $assessment
     */
    public function postUpdateAssessment(IAssessable $assessment)
    {
        /* Modify particular fields
         */
        if ($this->getRequest()->server['HTTP_X_HTTP_METHOD_OVERRIDE']) {
            $this->patchUpdateAssessment($assessment);
            return;
        }

        /* Regular update
         */
        try {
            $assessmentInfo = $this->persistAssessment(
                $this->getUser(),
                $this->getRequest()->post,
                $assessment
            );
            $internalReturnUrl = $assessmentInfo[Redirect::RETURN_URL];
        }
        catch (DomainException $e) {
            $this->sendError('Could not save assessment', [ $e->getMessage() ]);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        $postReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);

        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
        } else {
            $returnUrl = $this->determineRedirect($assessment, $internalReturnUrl);
        }

        // we're done here
        $this->redirect($returnUrl);
    }

    /**
     * Delete the given assessment.
     * Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     *
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond with '4XX' or '5XX' as appropriate
     *
     * @api   POST /learning/assessments/{assessmentId}/delete
     * @level 165
     * @param IAssessable $assessment
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment)
    {
        // If not already deleted?
        if (!$assessment->isDeleted()) {
            $this->getAssessmentMapper()->delete($assessment);
        }

        $folder = $assessment->getContext()->getFolder();
        $postReturnUrl = $this->getRequest()->query[Redirect::RETURN_URL];

        /* Check for the presence of a returnUrl passed on GET request as well
         */

        // Did caller explicitly specify a returnUrl ?
        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
        } else {
            $returnUrl = $this->determineRedirect($assessment, "/homepage/{$folder->getId()}");
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                Redirect::RETURN_URL => $returnUrl
            ]));
        }
        else {
            $this->redirect($returnUrl);
        }
    }

    /**
     * Redirect method
     *
     * Sends you to a status or summary screen depending on who is
     * currently logged in and what they are viewing
     *
     * @api GET /learning/assessment/:assessmentId
     * @param IAssessable $assessment
     */
    public function getAssessment(IAssessable $assessment)
    {
        /* If you're a staff, get redirected to a Status overview
         */
        if ($this->getUser()->getRole()->isStaff()) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]));
            return;
        }
        /* If you're a student, get redirected to the summary
         */
        else if ($this->getUser()->getRole()->isStudent()) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $this->getUser()->getId()]));
            return;
        }

        /* Everyone else sees The Nothing
         */
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
    }

    /**
     * @param AssessmentContext $context
     * @param IAssessable       $assessment
     * @return array
     */
    private function getAssessmentAsTwigArray(AssessmentContext $context, IAssessable $assessment)
    {
        if (!$assessment->getId()) {
            // new assessment: set default values
            $assessment
                ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setDueAt(new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);
        }

        $contextType = AssessmentContextType::objectToString($context) . 's';

        /* The usual info everything requires
         */
        $data = [
            'context'         => $context,
            'contextType'     => $contextType,
            'baseContextType' => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'     => $this->getBakery()->produceFromContext($context, $assessment),
            'assessment'      => $assessment,
            'folder'          => $context->getFolder(),
            'assessmentType'  => AssessmentType::objectToString($assessment),
            'visibility'      => $assessment->getMarkVisibility(),
        ];

        $data = array_merge($data, $this->getAssessmentTwigData($assessment));

        return $data;
    }

    /**
     * Return whether or not the given user is allowed to see the given participation
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
     */
    protected function userCanViewParticipation(User $user, Participant $participant)
    {
        $student = $participant->getUser();

        // staff can always see
        if ($user->getRole()->isStaff()) {
            return true;
        }

        // student can see own
        if ($student->getId() == $user->getId()) {
            return true;
        }

        // filter guests, and other students
        if (!$user->getRole()->isParent()) {
            return false;
        }

        // parent can see ... IF config allows
        if ($this->getConfigLoader()->get('restrict_student_submission')) {
            return false;
        }

        if ($this->getConfigLoader()->get('restrict_parent_duework_access')) {
            return false;
        }

        // parents are limited to their own children (this is expensive so definitely do this last!)
        $student->loadParents();
        if ($student->isChildOf($user)) {
            return true;
        }

        // failed all checks: bounce 'em!
        return false;
    }

    /**
     * Assert that the current session user is allowed to see the given participation
     * TODO consider pushing this up into the module
     *
     * @param Participant $participant
     */
    protected function assertParticipantAccess(Participant $participant)
    {
        if (!$this->userCanViewParticipation($this->getUser(), $participant)) {
            // could probably throw a 403, but that would give away the fact that, yes, the student is a participant of that assessment
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
     */
    protected function getAllMarkTypes(IAssessable $assessable)
    {
        return MarkSchemeFactory::getAvailableMarkTypes($assessable);
    }

    /**
     * Gets all work types for due work.
     *
     * TODO DI this if you feel like it
     *
     * @return WorkType[]|Collection
     */
    protected function getAllWorkTypes()
    {
        return WorkTypeMapper::getInstance()->findAll();
    }

    /**
     * Gets all grading schemes.
     *
     * TODO DI this if you feel like it
     *
     * @return GradeScheme[]|Collection
     */
    protected function getAllGradeSchemes()
    {
        return GradeSchemeMapper::getInstance()->findAllWithGradeLevels();
    }

    /**
     * @return BreadcrumbFactory
     */
    protected function getBakery()
    {
        return $this->bakery;
    }

    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected abstract function getAssessmentTwigData(IAssessable $assessment);

    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $defaultReturnUrl = '')
    {
        // if your context is a project, redirect back to its status screen
        if ($assessment->getContext() instanceof Project) {
            return $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getContext()->getId() ]);
        }
        return "/homepage/{$assessment->getContext()->getBaseContext()->getFolder()->getId()}";
    }

    /**
     * @param IMarkScheme $scheme
     * @return string
     */
    protected function deriveSchemeName(IMarkScheme $scheme)
    {
        $c = get_class($scheme);
        return substr($c, strrpos($c, '\\')+1);
    }

    /**
     * Update particular fields of an existing IAssessable
     *
     * Default Abstract implementation does nothing, Concrete class to override.
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function patchUpdateAssessment(IAssessable $assessment)
    {
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }
}
