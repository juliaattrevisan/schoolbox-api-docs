<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress\Web\Controller\Exception as ControllerException;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Mapper\Exception as MapperException;


/**
 * Placeholder for the ~170 Quiz Re-write
 */
class Controller extends AbstractController
{
    /** @var ErrorMapper */
    private $errorMapper;

    /** @var QuizSectionMapper */
    private $quizSectionMapper;

    /** @var QuizQuestionMapper */
    private $quizQuestionMapper;

    /** @var QuizSolutionMapper */
    private $quizSolutionMapper;

    /** @var QuizAttemptMapper */
    private $quizAttemptMapper;

    /** @var QuizAnswerMapper */
    private $quizAnswerMapper;


    /**
     * @param Mapper $quizMapper
     * @param ErrorMapper $errorMapper
     * @param QuizSectionMapper $quizSectionMapper
     * @param QuizQuestionMapper $quizQuestionMapper
     * @param QuizSolutionMapper $quizSolutionMapper
     * @param QuizAttemptMapper $quizAttemptMapper
     * @param QuizAnswerMapper $quizAnswerMapper
     */
    public function __construct(
        Mapper $quizMapper,
        ErrorMapper $errorMapper,
        QuizSectionMapper $quizSectionMapper,
        QuizQuestionMapper $quizQuestionMapper,
        QuizSolutionMapper $quizSolutionMapper,
        QuizAttemptMapper $quizAttemptMapper,
        QuizAnswerMapper $quizAnswerMapper
    )
    {
        parent::__construct($quizMapper);
        $this->errorMapper = $errorMapper;
        $this->quizSectionMapper = $quizSectionMapper;
        $this->quizQuestionMapper = $quizQuestionMapper;
        $this->quizSolutionMapper = $quizSolutionMapper;
        $this->quizAttemptMapper = $quizAttemptMapper;
        $this->quizAnswerMapper = $quizAnswerMapper;
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
     *
     * GETs the page where a Teacher can review a Student's attempt
     *
     * @api   GET /learning/quiz/review/{assessmentId}/{firstSectionId}/{responseId}
     * @since 165
     * @param int $assessmentId
     * @param int $firstSectionId
     * @param int $responseId
     * @todo Rebuild with twig
     */
    public function getQuizResponseFirstSection($assessmentId, $firstSectionId, $responseId)
    {
        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $this->getResponse()->redirect->to("/assess/review/$assessId/$firstSectionId/$responseId");
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
     *
     * GETs the page where a Teacher can add Section and Questions to the Quiz
     *
     * @api   GET /learning/quiz/summary/{assessmentId}
     * @since 165
     * @param int $assessmentId
     * @todo Rebuild with twig
     */
    public function getQuizSummary($assessmentId)
    {
        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        $redirect = $returnUrl ? '?'.Redirect::RETURN_URL."=$returnUrl" : '';
        $this->getResponse()->redirect->to("/assess/summary/$assessId{$redirect}");
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
     *
     * GETs the page where a Teacher can add Section and Questions to the Quiz
     *
     * @api   GET /learning/quiz/summary/{assessmentId}
     * @since 165
     * @param int $assessmentId
     * @todo Rebuild with twig
     */
    public function getQuizSectionCreate($assessmentId)
    {
        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        $redirect = $returnUrl ? '?'.Redirect::RETURN_URL."=$returnUrl" : '';
        $this->getResponse()->redirect->to("/assess/section/create/$assessId{$redirect}");
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
     *
     * GETs the page where a Teacher can add Section and Questions to the Quiz
     *
     * @api   GET /learning/quiz/review/{assessmentId}/horizontal
     * @since 165
     * @param int $assessmentId
     * @todo Rebuild with twig
     */
    public function getQuizReviewHorizontal($assessmentId)
    {
        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $this->getResponse()->redirect->to("/assess/reviewHorizontal/$assessId");
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
     *
     * Enables a Student to begin a Quiz
     *
     * @api   GET /learning/quiz/intro/{assessmentId}
     * @since 165
     * @param int $assessmentId
     * @todo Rebuild with twig
     */
    public function getQuizIntro($assessmentId)
    {
        $returnUrlSuffix = "?returnUrl=" . $this->getRequest()->query->get('returnUrl') ?: Redirect::encode($this->getRequest()->query->get('page'));

        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $this->getResponse()->redirect->to("/assess/intro/$assessId".$returnUrlSuffix);
    }

    /**
     * @api   GET /learning/quiz/{assessmentId}/{userId}/attempts
     * @param int $assessmentId
     * @param int $userId
     */
    public function getQuizAttemptsByUser($assessmentId, $userId)
    {
        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
        $this->getResponse()->redirect->to("/assess/attempts/$assessId/$userId");
    }
    /**
     * Exports all answers given to the specified quiz
     *
     * Format of export derived from GET param ('csv', 'xml', etc)
     *
     * @api   GET /learning/quiz/{assessmentId}/attempts/export?format=csv
     * @since 165
     * @param int $assessmentId
     */
    public function getExportAttempts($assessmentId)
    {
        $format = strtolower($this->getRequest()->query->get('format'));
        if(!in_array($format, ['csv', 'xml'])){
            throw new \InvalidArgumentException("Unsupported export format: $format");
        }
        $quiz = $this->getQuizForAssessmentId($assessmentId);
        $this->getResponse()->redirect->to("/assess/export/$format/{$quiz->getId()}");
    }


    /**
     * Converts an assessment_id to an assess_id
     *
     * @param int $assessmentId
     * @return Model
     */
    private function getQuizForAssessmentId($assessmentId)
    {
        /** @var Model $quiz */
        $quiz = $this->mapper->findByColumn('assessment_id', $assessmentId)->first();

        if(!$quiz){
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        if (Model::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }

    /**
     * Marks an attempt as completed
     *
     * @var int    $_POST['attemptId']
     * @var string $_POST['returnUrl']
     */
    public function postQuizSubmit()
    {
        $get = $this->getRequest()->query;
        $post = $this->getRequest()->post;

        try {
            $attempt = $this->quizAttemptMapper->findById($post['attemptId']);
            $attempt->setFinish(\Alaress_Date_Format::now());
            $this->quizAttemptMapper->save($attempt);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_ATTEMPT_NOT_FOUND);
        }

        if($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput(['status' => 200]));
        } else {
            $this->getResponse()->redirect->to(Redirect::decode($get['returnUrl']));
        }
    }

    /**
     * Populate the Response object with a list of entities:
     *  - in the format provided in the request Accepts header
     *  - optionally based on search parameters provided in the query string
     *
     * @api   GET /learning/quiz
     * @since 165
     * @param object $context
     * @throws ControllerException
     * @return void
     * @todo declare type (Class? Teacher?) in method args
     */
    public function getList($context = null)
    {
        // TODO: Implement method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @api   GET /learning/quiz/{quizId}
     * @since 165
     * @param object $entity
     * @throws ControllerException
     * @return void
     * @todo declare type in method args
     */
    public function getEntity($entity)
    {
        // TODO: Implement method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * @api   GET /learning/quiz/new
     * @api   GET /learning/quiz/{quizId}/edit
     * @since 165
     * @param object $entity
     * @param object|null $context
     * @todo declare types in method args
     */
    public function getForm($entity, $context = null)
    {
        // TODO: Implement getForm() method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * Persist the given entity. If the persistence...
     *  - SUCCEEDS - populate the Response with a '201 Created' status and a Location header pointing to the freshly-created resource
     *  - FAILS    - respond appropriately - some specific codes to consider before falling back to generics
     *     - 422 Unprocessable Entity - general validation fail
     *
     * @api   POST /learning/quiz
     * @since 165
     * @param object $entity
     * @throws ControllerException
     * @return void
     * @todo declare type in method args
     */
    public function createEntity($entity)
    {
        // TODO: Implement method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        //$this->persistEntity($entity);
    }

    /**
     * Persist the given pre-existing entity. If the persist...
     *  - SUCCEEDS - populate the Response with a '204 No Content' status and a Location header pointing to the updated resource
     *  - FAILS    - respond appropriately - some specific codes to consider before falling back to generics
     *     - 409 Conflict             - specific validation fail: someone else modified this entity under your feet
     *     - 422 Unprocessable Entity - general validation fail
     *     - 410 Gone                 - entity is deleted (soft or otherwise)
     *
     * @api   POST /learning/quiz/{quizId}
     * @since 165
     * @param object $entity
     * @throws ControllerException
     * @return void
     * @todo declare type in method args
     */
    public function updateEntity($entity)
    {
        // TODO: Implement method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        //$this->persistEntity($entity);
    }

    /**
     * @param object $entity
     * @todo declare type in method args
     */
    protected function persistEntity($entity)
    {
        // TODO: Implement method.
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * Delete the given entity. Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond appropriately
     *
     * @api   POST /learning/quiz/{quizId}/delete
     * @since 165
     * @param object $entity
     * @throws ControllerException
     * @return void
     * @todo declare type in method args
     */
    public function deleteEntity($entity)
    {
        // TODO: Remove method to revert to abstract implementation
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * @api   POST /quiz/attempts/delete
     */
    public function postAttemptDelete()
    {
        try {
            $attempt = $this->quizAttemptMapper->findById($this->getRequest()->post['attemptId']);
            $this->quizAttemptMapper->delete($attempt);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_ATTEMPT_NOT_FOUND);
        }
    }

    /* Getters / Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
