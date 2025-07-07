<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Alaress\Lumberjack\Logger;
use Aura\Router\Exception\RouteNotFound;
use League\Flysystem\FilesystemException;
use LearnositySdk\Exceptions\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress\Web\Controller\Exception as ControllerException;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\Legacy;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Twig\Error\Error;

/**
 * Placeholder for the ~170 Quiz Re-write
 */
class Controller extends AbstractController
{
    /**
     * Constructor.
     *
     * @param \Schoolbox\Learning\Quiz\Mapper                           $quizMapper
     * @param ErrorMapper                                               $errorMapper
     * @param QuizSectionMapper                                         $quizSectionMapper
     * @param QuizQuestionMapper                                        $quizQuestionMapper
     * @param QuizSolutionMapper                                        $quizSolutionMapper
     * @param QuizAttemptMapper                                         $quizAttemptMapper
     * @param QuizAnswerMapper                                          $quizAnswerMapper
     * @param AssessmentMapperProxy                                     $assessmentMapperProxy
     * @param \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $projectResultCacheMapper
     * @param \Schoolbox\Learning\Assessment\WorkType\Mapper            $workTypeMapper
     * @param MarkTypeMapper                                            $markTypeMapper
     * @param \Schoolbox\Resource\Storage\Mapper                        $storageMapper
     * @param \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper
     * @param \Schoolbox\Curriculum\Node\Mapper                         $curriculumNodeMapper
     * @param string                                                    $learnosityVersion
     * @param string                                                    $learnosityConsumerKey
     * @param string                                                    $learnosityConsumerSecret
     */
    public function __construct(
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private ErrorMapper $errorMapper,
        private QuizSectionMapper $quizSectionMapper,
        private QuizQuestionMapper $quizQuestionMapper,
        private QuizSolutionMapper $quizSolutionMapper,
        private QuizAttemptMapper $quizAttemptMapper,
        private QuizAnswerMapper $quizAnswerMapper,
        private \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy,
        private \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        private \Schoolbox\Learning\Assessment\WorkType\Mapper $workTypeMapper,
        private \Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper $markTypeMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper,
        private string $learnosityVersion,
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
    ) {
        parent::__construct($quizMapper);
    }

//    /**
//     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
//     *
//     * GETs the page where a Teacher can review a Student's attempt
//     *
//     * @api   GET /learning/quiz/review/{assessmentId}/{firstSectionId}/{responseId}
//     * @since 165
//     * @param int $assessmentId
//     * @param int $firstSectionId
//     * @param int $responseId
//     * @todo Rebuild with twig
//     */
//    public function getQuizResponseFirstSection($assessmentId, $firstSectionId, $responseId)
//    {
//        $assessId       = $this->getQuizForAssessmentId($assessmentId)->getId();
//        $this->response = $this->redirectFound("/assess/review/$assessId/$firstSectionId/$responseId");
//    }

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
        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        $redirect = $returnUrl ? '?' . Redirect::RETURN_URL . "=$returnUrl" : '';

        $url = $this->router->getGenerator()->generate('learnosity.getContent', [
            'assessmentId' => $assessmentId
        ]);

        $this->response = $this->redirectFound("{$url}{$redirect}");
    }

//    /**
//     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
//     *
//     * GETs the page where a Teacher can add Section and Questions to the Quiz
//     *
//     * @api   GET /learning/quiz/summary/{assessmentId}
//     * @since 165
//     * @param int $assessmentId
//     * @todo Rebuild with twig
//     */
//    public function getQuizSectionCreate($assessmentId)
//    {
//        $assessId = $this->getQuizForAssessmentId($assessmentId)->getId();
//        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
//        $redirect = $returnUrl ? '?' . Redirect::RETURN_URL . "=$returnUrl" : '';
//        $this->response = $this->redirectFound("/assess/section/create/$assessId{$redirect}");
//    }
//
//    /**
//     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
//     *
//     * GETs the page where a Teacher can add Section and Questions to the Quiz
//     *
//     * @api   GET /learning/quiz/review/{assessmentId}/horizontal
//     * @since 165
//     * @param int $assessmentId
//     * @todo Rebuild with twig
//     */
//    public function getQuizReviewHorizontal($assessmentId)
//    {
//        $assessId       = $this->getQuizForAssessmentId($assessmentId)->getId();
//        $this->response = $this->redirectFound("/assess/reviewHorizontal/$assessId");
//    }
//
//    /**
//     * Maps a Quiz's AssessmentId onto an equivalent URL using the old assess_id
//     *
//     * Enables a Student to begin a Quiz
//     *
//     * @api   GET /learning/quiz/intro/{assessmentId}
//     * @since 165
//     * @param int $assessmentId
//     * @todo Rebuild with twig
//     */
//    public function getQuizIntro($assessmentId)
//    {
//        $returnUrlSuffix = "?returnUrl=" .
//            ($this->requestQuery->get('returnUrl') ?: Redirect::encode(RequestUriParser::parseUrlPath($this->request->getServerParams())));
//
//        $assessId       = $this->getQuizForAssessmentId($assessmentId)->getId();
//        $this->response = $this->redirectFound("/assess/intro/$assessId" . $returnUrlSuffix);
//    }
//
//    /**
//     * @api   GET /learning/quiz/{assessmentId}/{userId}/attempts
//     * @param int $assessmentId
//     * @param int $userId
//     */
//    public function getQuizAttemptsByUser($assessmentId, $userId)
//    {
//        $assessId       = $this->getQuizForAssessmentId($assessmentId)->getId();
//        $this->response = $this->redirectFound("/assess/attempts/$assessId/$userId");
//    }

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
        $format = strtolower($this->requestQuery->get('format'));
        if (!in_array($format, ['csv', 'xml'])) {
            throw new \InvalidArgumentException("Unsupported export format: $format");
        }
        $quiz           = $this->getQuizForAssessmentId($assessmentId);
        $this->response = $this->redirectFound("/assess/export/$format/{$quiz->getId()}");
    }

    /**
     * Converts an assessment_id to an assess_id
     *
     * @param int $assessmentId
     * @return Model
     * @throws \Exception
     */
    private function getQuizForAssessmentId($assessmentId)
    {
        $quizzes = $this->mapper->findByColumn('assessment_id', $assessmentId);

        if (count($quizzes) === 0) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /** @var Model $quiz */
        $quiz = $quizzes->first();

        if (Model::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        return $quiz;
    }


    /**
     * Imports a quiz into the given context via its legacy xml representation.
     *
     * @api POST /learning/quiz/legacyQuizImport/contextType/contextId
     *
     * @param AssessmentContext $context
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Deleted
     * @throws FilesystemException
     * @throws MapperException
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws \DOMException
     * @throws \JsonException
     * @throws ValidationException
     * @throws Error
     */
    public function postImport(AssessmentContext $context): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* No upload? No trabajo
         */
        if (!isset($postData['attachment'])) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
        }

        /* An unusual outcome where the key might exist, but nothing else is in
         * the way we thought - probably our mistake
         */
        if (!is_array($postData['attachment'])) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
        }

        /* Milk out some of the assessment settings from the xml itself
         */
        try {
            /** @var \Schoolbox\Resource\Storage\Model $file */
            $file = (new CollectorFactory())->build($postData['attachment'] ?: [])->getFirst();

            /** @var \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy */
            $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');
            $data            = $filesystemProxy->read($file);
        } catch (\Schoolbox\Resource\Exception $e) {
            /* Something went wrong reading the file's contents
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
        }

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* Used to segregate each school's data; used in tags and author identifiers
         */
        $schoolCode = $this->getConfigLoader()->get('site_school_code');

        /* For converting urls
         */
        $server     = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $serverName = $server->getHost();
        $domain     = (string)$server;

        $api = new Api(
            $serverName,
            $this->learnosityVersion,
            $this->learnosityConsumerKey,
            $this->learnosityConsumerSecret,
            null
        );

        /* Detect whether this is a json or xml file
         * The presence of <?xml version="1.0" ?> denotes xml;
         * otherwise, presume json
         */
        if (preg_match('/^<\?xml version="1\.0" \?>/', $data)) {
            /* Intentionally prefill with an instance of AssessmentContext
             * and other POST vars the Factory assumes exist
             */
            $postData = array_merge($this->requestPost->getArrayCopy(), [
                'assessmentType' => AssessmentType::TYPE_QUIZ,
                'context'        => $context,
                /* Legacy xml only ever supported Total Mark marktype
                 */
                'markType'       => (string)MarkType::TYPE_TOTAL
            ]);

            /* A 4-tuple of useful things we made
             * Will likely be empty if something snapped along the way
             */
            $result = [];

            try {
                $result = (new Legacy\Xml\Importer(
                    $api,
                    $schoolCode,
                    $domain,
                    $this->getDi()->get('databaseInternal'),
                    $context,
                    $this->getUser(),
                    $assessmentMapperProxy,
                    $this->quizMapper,
                    $this->workTypeMapper,
                    $this->markTypeMapper,
                    $this->storageMapper
                ))->import($data);

                /* Distill anything mostly non-fatal that we found during the import process
                 */
                $errors = $result['errors'];
            } catch (\DomainException $e) {
                /* Something really important snapped and halted the import process
                 */
                $errors = [
                    $e->getMessage()
                ];
            }

            /* Display a list of errors
             */
            if (\count($errors) || empty($result)) {
                $this->response = $this->responseFromTemplate('@learningQuiz/xmlImportLog.twig', [
                    'errors' => $errors,
                ]);

                return;
            }

            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'learningAssessment.getAssessment',
                    ['assessmentId' => $result['assessment']->getId()]
                )
            );
        } else {
            /* There used to be stuff here that was used to convert url domains but I'm not sure we even
             * needed them. We needed them in the xml days, but I don't think we needed them here.
             */
            $newInstances = (new Importer\Json\Importer(
                $api,
                $schoolCode,
                $this->getDi()->get('databaseInternal'),
                $context,
                $this->getUser(),
                $assessmentMapperProxy,
                $this->quizMapper,
                $this->workTypeMapper,
                $this->markTypeMapper,
                $this->gradeGroupMapper,
                $this->storageMapper,
                $this->curriculumNodeMapper,
            ))->import($data);

            /* Check for errors: if there were any, go to a new screen to explain this.
             * Otherwise just redirect back to the quiz you just made...its modify screen, I guess.
             */
            if (\count($newInstances['errors'])) {
                /* Breadcrumbs
                 */
                $breadcrumbs = $this->bakery->produceFromAssessment($newInstances['assessment'], $this->getUser())
                    ->add([
                        $newInstances['assessment']->getTitle(),
                        $newInstances['assessment']->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $newInstances['assessment']->getId()]) : null
                    ])
                    ->add(['Import', null]);

                $this->response = $this->responseFromTemplate('@learnosity/importErrors.twig', [
                    'breadcrumbs' => $breadcrumbs,
                    'assessment' => $newInstances['assessment'],
                    'errors' => $newInstances['errors']
                ], 'Import errors');
            } else {
                $returnUrlEncoded = $this->requestPost['returnUrl'];

                if ($returnUrlEncoded !== null) {
                    /* Go back to the place from whence you came
                     */
                    $returnUrl = Redirect::decode($returnUrlEncoded);
                } else {
                    /* Otherwise redirect back to the quiz you just made...its modify screen, I guess.
                     */
                    $returnUrl = $this->routeGenerator->generate('learningAssessment.getUpdateAssessmentForm', ['assessmentId' => $newInstances['assessment']->getId()]);
                }

                $this->response = $this->redirectFound($returnUrl);
            }
        }
    }


//    /**
//     * Marks an attempt as completed
//     *
//     * @var int    $_POST['attemptId']
//     * @var string $_POST['returnUrl']
//     */
//    public function postQuizSubmit()
//    {
//        $get = $this->requestQuery;
//        $post = $this->requestPost;
//
//        try {
//            $attempt = $this->quizAttemptMapper->findById($post['attemptId']);
//            $attempt->setFinish(\Alaress_Date_Format::now());
//            $this->quizAttemptMapper->save($attempt);
//        } catch (MapperException $e) {
//            throw $this->errorMapper->generateException(ErrorMapper::ERROR_ATTEMPT_NOT_FOUND);
//        }
//
//        if ($this->isXhrRequest()) {
//            $this->response = $this->responseAsJson(['status' => 200]);
//        } elseif (isset($get['returnUrl'])) {
//            $this->response = $this->redirectFound(Redirect::decode($get['returnUrl']));
//        } else {
//            $this->response = $this->redirectFound($this->getSession()->getHistory());
//        }
//    }

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
            $attempt = $this->quizAttemptMapper->findById($this->requestPost['attemptId']);
            $this->quizAttemptMapper->delete($attempt);

            /* Invalidate project result cache if this quiz is in a project
             * (This is what I have to do here...you've gotta be kidding me)
             */
            $assessment = $this->assessmentMapperProxy->findById($attempt->getQuiz()->getAssessmentModel()->getId());

            (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))->refresh($this->assessmentMapperProxy, $assessment, $attempt->getUser());
        } catch (MapperException $e) {
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
