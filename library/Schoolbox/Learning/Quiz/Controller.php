<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress\Web\Controller\Exception as ControllerException;


/**
 * Placeholder for the ~170 Quiz Re-write
 */
class Controller extends AbstractController
{
    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @param OnlineAssessmentMapper $quizMapper
     * @param ErrorMapper $errorMapper
     */
    public function __construct(OnlineAssessmentMapper $quizMapper, ErrorMapper $errorMapper)
    {
        parent::__construct($quizMapper);
        $this->errorMapper = $errorMapper;
    }

    /**
     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
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
        /** @var OnlineAssessment $quiz */
        $quiz = $this->mapper->findByColumn('assessment_id', $assessmentId)->first();

        if (OnlineAssessment::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        $assessId = $quiz->getId();

        $this->getResponse()->redirect->to("/assess/review/$assessId/$firstSectionId/$responseId");
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