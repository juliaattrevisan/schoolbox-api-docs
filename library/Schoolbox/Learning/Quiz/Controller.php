<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress\Web\Controller\Exception as ControllerException;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Model as QuizSolution;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * Builds the Quiz graph. Attaches and links Sections, Questions, Solutions, Attempts & Answers
     *
     * Edge cases:
     * * An Attempt my be for a subset of Questions available in a Quiz
     * * An Attempt may have Answers to Sections/Questions that are no longer in a Quiz
     *
     * @param Model $quiz
     * @return Model
     */
    public function buildQuizGraph(Model $quiz)
    {
        // Add Attempts to Quiz (historical view)
        /** @var QuizAttempt[] $attempts */
        $attempts = $this->quizAttemptMapper->findByColumn('assess_id', $quiz->getId());
        foreach($attempts as $attempt){
            $attempt->setQuiz($quiz);
            $quiz->getAttempts()->setOffset($attempt->getId(), $attempt);
        }

        // Add Answers to Attempts (historical view)
        /** @var QuizAnswer[] $answers */
        $answers = $this->quizAnswerMapper->findByColumn('attempt_id', $quiz->getAttempts()->keys());
        $answeredQuestionIds = [];
        foreach($answers as $answer){
            $answeredQuestionIds[] = $answer->getQuestion()->getId();

            /** @var QuizAnswer $answer */
            $attempt = $quiz->getAttempts()->getOffset($answer->getAttempt()->getId());
            $attempt->getAnswers()->setOffset($answer->getId(), $answer);
            $answer->setAttempt($attempt);
        }

        // Get all answered Questions (historical view)
        $columnName = $this->quizQuestionMapper->getIdentifierColumn();
        /** @var QuizQuestion[]|Collection $answeredQuestions */
        $answeredQuestions = $this->quizQuestionMapper->findByColumn($columnName, array_unique($answeredQuestionIds));

        $answeredSectionIds = [];
        foreach ($answeredQuestions as $answeredQuestion) {
            $answeredSectionIds[] = $answeredQuestion->getSection()->getId();
        }

        // Get all answered Sections (historical view)
        $columnName = $this->quizSectionMapper->getIdentifierColumn();
        /** @var QuizSection[]|Collection $answeredSections */
        $answeredSections = $this->quizSectionMapper->findByColumn($columnName, array_unique($answeredSectionIds));
        foreach ($answeredSections as $answeredSection) {
            $quiz->getSections()->setOffset($answeredSection->getId(), $answeredSection);
            $answeredSection->setQuiz($quiz);
        }

        /**
         * Maintain a cache so we can later link Questions and Answers.
         *
         * @var QuizQuestion[]|Collection $allQuestionsIndexedById
         */
        $allQuestionsIndexedById = $this->quizQuestionMapper->createCollection();

        // Add answered Questions to Sections (historical view)

        foreach ($answeredQuestions as $answeredQuestion) {
            $allQuestionsIndexedById[$answeredQuestion->getId()] = $answeredQuestion;
            /** @var QuizSection $answeredSection */
            $answeredSection = $quiz->getSections()->getOffset($answeredQuestion->getSection()->getId());
            $answeredSection->getQuestions()->setOffset($answeredQuestion->getId(), $answeredQuestion);
            $answeredQuestion->setSection($answeredSection);
        }

        // Get all Sections in the Quiz (transient view)
        /** @var QuizSection[] $sections */
        $sections = $this->quizSectionMapper->findByColumn('assess_id', $quiz->getId());
        foreach($sections as $section){
            if(!$quiz->getSections()->getOffset($section->getId()) instanceof QuizSection){
                $quiz->getSections()->setOffset($section->getId(), $section);
                $section->setQuiz($quiz);
            }
        }

        // Get all Questions for the Sections (transient view)
        /** @var QuizQuestion[] $questions */
        $questions = $this->quizQuestionMapper->findByColumn('section_id', $quiz->getSections()->keys());

        foreach($questions as $question){
            /** @var QuizSection $section */
            $section = $quiz->getSections()->getOffset($question->getSection()->getId());
            if(!$section->getQuestions()->getOffset($question->getId()) instanceof QuizQuestion){
                $allQuestionsIndexedById[$question->getId()] = $question;
                $section->getQuestions()->setOffset($question->getId(), $question);
                $question->setSection($section);
            }
        }

        // Link Questions and Answers
        foreach($answers as $answer){
            $question = $allQuestionsIndexedById[$answer->getQuestion()->getId()];
            $answer->setQuestion($question);
            $question->getAnswers()->setOffset($answer->getId(), $answer);
        }

        return $quiz;
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

        return $this->getResponse()->redirect->to(
            Redirect::decode($get['returnUrl'])
        );
    }

    /**
     * Populate the Response object with a list of entities:
     *  - in the format provided in the request Accepts header
     *  - optionally based on search parameters provided in the query string
     *
     * @api   GET /learning/quiz
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @level 165
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
     * @api   POST /quiz/attempts/{attemptId}/delete/
     */
    public function postAttemptDelete()
    {
        new \Alaress_Probe($this->getRequest()->post);
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
