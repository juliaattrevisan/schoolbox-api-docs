<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Html2Text\Html2Text;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\DifferentiationProfile\FileParser\ParserFactory;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as DifferentiationProfileAdminMapper;
use Schoolbox\DifferentiationProfile\Translator\ProfileToArray;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\OpenAi\Agent\CkEditorTextChat;
use Schoolbox\OpenAi\Agent\DifferentiationProfileGeneration;
use Schoolbox\OpenAi\Agent\ImageGeneration;
use Schoolbox\OpenAi\Agent\LearningActivityCustomisationGeneration;
use Schoolbox\OpenAi\Agent\LessonPlanGeneration;
use Schoolbox\OpenAi\ClientFactory;
use Schoolbox\OpenAi\Exception as AiException;
use Schoolbox\OpenAi\Feature;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Presidio\Client;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;

/**
 * This controller acts as a proxy for OpenAI API.  It just appends the configured API key to the request in
 * order to keep it secret. Then forwards the request and sends the contents back to user.  This ensures that the
 * users are always communicating via our server, using our key and in a controlled way.
 *
 * Future enhancements may include:
 *  * Request logging
 *  * Guardrails to prevent inappropriate usage
 *  * Rate limiting
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper,
        private PastoralMapper $pastoralMapper,
        private FilesystemProxy $filesystemProxy,
        private ParserFactory $parserFactory,
        private ClientFactory $clientFactory,
        private ?Client $presidioClient,
        private DifferentiationProfileMapper $differentiationProfileMapper,
        private DifferentiationProfileAdminMapper $differentiationProfileAdminMapper,
        private FolderMapper $folderMapper,
        private MapperProxy $assessmentMapperProxy,
        private CacheInterface $aiCache,
    ) {
    }


    /**
     * Generates a payload about lesson plans
     *
     * @return void
     * @throws ClientExceptionInterface
     * @throws Deleted
     * @throws Exception
     * @throws JsonException
     */
    public function postLessonPlan(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new Config();
        $lpConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_LESSONPLAN));

        try {
            /** gen a sessionID guid for keeping track of what is happening
             * for lessonplan each ai request is a new session
             */
            $aiSessionId = (string)Uuid::uuid4();

            $lessonPlanAgent = new LessonPlanGeneration(
                $this->clientFactory->buildForFeature(Feature::LESSON_PLAN),
                $lpConfig,
                trim((string)$this->getConfigLoader()->get('spellcheck_language')),
                $this->curriculumNodeMapper,
            );

            try {
                [$response, $status] = $lessonPlanAgent->request(
                    $this->user,
                    $this->requestPost->get('templateTitle'),
                    $this->requestPost->get('lessonPlanTitle'),
                    $this->requestPost->get('context'),
                    $this->requestPost->get('fields'),
                    $this->requestPost->get('duration'),
                    $this->requestPost->get('curriculumNodeIds'),
                    $this->requestPost->get('additionalContext') ?? '',
                    $this->requestPost->get('generateForField') ?? null,
                    $aiSessionId,
                );

                $this->response = $this->responseAsJson($response)->withStatus($status);
            } catch (SchoolboxException $e) {
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus($e->getCode());

                return;
            }
            return;
        } catch (AiException\Runtime $e) {
            /* Not configured? Not found
             */
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }


    /**
     * Generates an image as part of a conversation
     *
     * @return void
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function postImage(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // some images may take quite some time, avoid the request stopping early
        $this->extendExecutionTime();

        try {
            // use the frontend's aiSessionId to keep track of conversations
            $sessionId = $this->requestPost->get('aiSessionId');
            // we should have an aiSessionId generated by the frontend.. if not, we should error,
            // this shouldn't happen, it's probably someone manually hitting the API, send them this error.
            if (!$sessionId || !Uuid::isValid($sessionId)) {
                $problem = (new ApiProblem("AI Session id is required and must be a valid UUID."))
                    ->setStatus(400)
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus(400);

                return;
            }

            $imageGenerationConfig = new Config()->decode(
                (string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_IMAGE_GENERATION)
            );
            $agent = new ImageGeneration(
                $this->clientFactory->buildForFeature(Feature::IMAGE),
                $imageGenerationConfig,
            );
            try {
                // Size and style need to be converted
                $response = $agent->requestStream(
                    $this->getUser(),
                    $this->requestPost->get('userPrompt'),
                    $sessionId,
                    size: ($this->requestPost->get('attributes') ?? [])['size'] ?? null,
                    style: ($this->requestPost->get('attributes') ?? [])['style'] ?? null,
                    imageUrl: $this->requestPost->get('imageUrl') ?? null,
                    previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
                );

                // The iterable response can just be passed straight to
                // responseFromStream
                $this->response = $this->responseAsEventStream($response);
                return;
            } catch (SchoolboxException $e) {
                // This will always be a stream
                // Unfortunately the exception isn't raised until the response
                // is output, which doesn't happen until after this method
                // returns.
                $problem = (new ApiProblem($e->getMessage()))
                    ->setStatus($e->getCode())
                    ->asArray();

                $this->response = $this
                    ->responseAsJson($problem)
                    ->withStatus($e->getCode());
                return;
            }
        } catch (AiException\Runtime $e) {
            // This will always be a stream
            /* Not configured? Not found
             */
            $returnCode = 404;
            $problem    = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this
                ->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }
    }


    /**
     * This is used by ckeditor's Little Robot Dude ai assistant which posts
     * to /openai route via some direct javascript thing
     *
     * @return void
     * @throws JsonException
     */
    public function postAiAssistant(): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new Config();
        $chatConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_TEXT_CHAT));

        /** gen a sessionID guid for keeping track of what is happening
         * for ckeditor chat each ai request is a new session
         */
        $aiSessionId = (string)Uuid::uuid4();

        $agent = new CkEditorTextChat(
            $this->clientFactory->buildForFeature(Feature::CHAT),
            $chatConfig,
            new Html2Text(),
        );

        try {
            $response = $agent->requestStream(
                $this->getUser(),
                $this->request->getParsedBody(),
                $aiSessionId
            );
            $this->response = $this->responseAsEventStream($response);
            return;
        } catch (AiException\Runtime $e) {
            /* Not configured? Not found
             */
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }

    public function postDifferentiation(): void
    {
        $this->setAllowBackToTheFuture(false);

        /* Presidio client is required for this feature
         * if not set then error out early
         */
        if (null === $this->presidioClient) {
            $returnCode = 500;

            $problem = (new ApiProblem("Presidio client is not configured."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }

        $aiConfig = new Config();
        $diffConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_DIFFERENTIATION));

        $aiSessionId = (string)Uuid::uuid4();

        $studentId = $this->requestPost->get('studentId');
        if (!$studentId) {
            $returnCode = 400;

            $problem = (new ApiProblem("Student Id is required."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }

        try {
            $records = $this->pastoralMapper->findByContributionDifferentiationProfile($studentId);

            /** @var User $student */
            $student = $this->differentiationProfileMapper->getJoinMapper('student')->findById($studentId);

            $accessList = $this->getUser()
                           ->getAccessList()
                           ->filterBy('service', UserAccess::SERVICE_DIFFERENTIATION);

            $hasAccess = false;
            $studentCampus = $student->getCampusCollection();
            foreach ($accessList as $access) {
                if (
                    $access->getAccessLevel() >= UserAccess::ACCESS_WRITE &&
                    $studentCampus->offsetExists($access->getServiceIdentifier())
                ) {
                    $hasAccess = true;
                    break;
                }
            }

            /* Ensure user has proper campus permission to generate this student's
             * differentiation profile
             */
            if (!$hasAccess) {
                $returnCode = 403;

                $problem = (new ApiProblem("Campus permission is required"))
                    ->setStatus($returnCode)
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                                       ->withStatus($returnCode);

                return;
            }

            $agent = new DifferentiationProfileGeneration(
                $this->clientFactory->buildForFeature(Feature::DIFFERENTIATION),
                $diffConfig,
                $this->filesystemProxy,
                $this->parserFactory,
                $this->presidioClient,
            );

            $enabledFields = $this->differentiationProfileAdminMapper
                ->findByColumnList(
                    [
                        'module' => DifferentiationProfileAdminMapper::MODULE_DETAILS,
                        'is_enabled' => 1,
                    ],
                );

            [$response, $status] = $agent->request(
                $this->getUser(),
                $records,
                $enabledFields,
                $student,
                $aiSessionId,
            );

            $this->response = $this->responseAsJson($response)->withStatus($status);
            return;
        } catch (\DomainException $e) {
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        } catch (\Throwable $e) {
            $returnCode = 500;

            $problem = (new ApiProblem("An unexpected error occurred."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus($returnCode);

            return;
        }
    }

    public function postLearningActivityCustomisation(): void
    {
        $this->setAllowBackToTheFuture(false);

        $aiConfig = new Config();
        $diffConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION));

        if ($aiConfig->isEnabled($diffConfig) === false) {
            $returnCode = 404;

            $problem = (new ApiProblem("AI Learning Activity Customisation is not enabled."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                                   ->withStatus($returnCode);

            return;
        }

        $studentId = $this->requestPost->getInt('studentId');
        if (!$studentId) {
            $returnCode = 400;

            $problem = (new ApiProblem("Student Id is required."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                                   ->withStatus($returnCode);

            return;
        }

        $assessmentId = $this->requestPost->getInt('assessmentId');
        if (!$assessmentId) {
            $returnCode = 400;

            $problem = (new ApiProblem("Assessment Id is required."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                                   ->withStatus($returnCode);

            return;
        }

        $cacheKey = sprintf(
            Helper::DIFFERENTIATION_PROFILE_CACHE_KEY,
            $assessmentId,
            $studentId
        );

        $cache = $this->aiCache->get($cacheKey);

        /* Allow bypassing the cache if regenerating
         */
        $bypassCache = $this->requestPost->getBool('regenerate', false);

        /* Early return if we have a cached response
         */
        if (null !== $cache && !$bypassCache) {
            $this->response = $this->responseAsJson($cache)
                                   ->withStatus(200);

            return;
        }

        $aiSessionId = (string)Uuid::uuid4();

        try {
            $assessment = $this->assessmentMapperProxy->findById($assessmentId);
            $differentiationProfile = $this->differentiationProfileMapper->findOneByColumn(
                'student_id',
                $studentId,
                [
                    'order' => 'created_at DESC',
                ]
            );

            /* Fully hydrate the differentiation profile with the student data
             */
            $differentiationProfile->setStudent(
                $this->differentiationProfileMapper
                    ->getJoinMapper('student')
                    ->findById($studentId)
            );

            $translator = new ProfileToArray(
                $this->getUser(),
                $this->differentiationProfileAdminMapper
                    ->findByColumn('is_enabled', 1),
            );

            $course = $this->folderMapper->findById($assessment->getContext()->getFolder()->getParent()->getId());

            $agent = new LearningActivityCustomisationGeneration(
                $this->clientFactory->buildForFeature(Feature::LEARNING_ACTIVITY_CUSTOMISATION),
                $diffConfig,
                $this->filesystemProxy,
                $this->parserFactory,
                $translator,
            );

            [$response, $status] = $agent->request(
                $this->getUser(),
                $assessment,
                $differentiationProfile,
                $course,
                $aiSessionId,
            );

            if (200 === $status) {
                /* Cache successful responses
                 */
                $this->aiCache->set(
                    $cacheKey,
                    $response,
                    Helper::DIFFERENTIATION_PROFILE_CACHE_DEFAULT_TTL
                );
            }

            $this->response = $this->responseAsJson($response)->withStatus($status);
            return;
        } catch (\DomainException $e) {
            $returnCode = 404;

            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                                   ->withStatus($returnCode);

            return;
        } catch (\Throwable $e) {
            $returnCode = 500;

            $problem = (new ApiProblem("An unexpected error occurred."))
                ->setStatus($returnCode)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                                   ->withStatus($returnCode);

            return;
        }
    }
}
