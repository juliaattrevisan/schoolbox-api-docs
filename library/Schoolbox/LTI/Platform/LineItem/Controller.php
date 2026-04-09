<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\GetLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\UpdateLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\Handler\LtiServiceServerRequestHandlerInterface;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\LineItem\SingleAssessmentRepository;
use Schoolbox\LTI\Score\SingleAssessmentScoreRepository;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\LTI\Util\LogHelper;

/**
 * LTI Platform controller for line items.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param LtiAssessment\Mapper            $ltiAssessmentMapper      LTI assessment mapper, for retrieving the
     *                                                                  assessment's details.
     * @param LtiAssessmentScore\Mapper       $ltiAssessmentScoreMapper LTI score mapper, for writing a mark for the
     *                                                                  assessment.
     * @param MapperProxy                     $assessmentDataSource     Data source for assessment objects: used by the
     *                                                                  LTI library for modifying LTI assessments.
     * @param RegistrationRepositoryInterface $registrations            Repository of tool+platform registrations.
     * @param UserCodec                       $userCodec                User codec, used to decode a user ID in the
     *                                                                  score publish request into an actual user.
     * @param CacheItemPoolInterface          $accessTokenCache         Cache for access tokens.
     * @param LoggerInterface                 $logger                   Logger for what this controller does.
     */
    public function __construct(
        private LtiAssessment\Mapper $ltiAssessmentMapper,
        private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        private MapperProxy $assessmentDataSource,
        private RegistrationRepositoryInterface $registrations,
        private UserCodec $userCodec,
        private CacheItemPoolInterface $accessTokenCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * LTI get line items service endpoint: currently not implemented.
     *
     * @api   GET /lti-platform/line-item/assessment/
     * @since 26.0
     * @return void
     */
    public function getLineItems(): void
    {
        $this->logger->error(__METHOD__ . ': not implemented');
        throw new NotFound();
    }

    /**
     * LTI post line item service endpoint: currently not implemented.
     *
     * @api   POST /lti-platform/line-item/assessment/
     * @since 26.0
     * @return void
     */
    public function postLineItem(): void
    {
        $this->logger->error(__METHOD__ . ': not implemented');
        throw new NotFound();
    }

    /**
     * LTI get line item service endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#example-getting-a-single-line-item
     *
     * @api   GET /lti-platform/line-item/assessment/{assessmentId}
     * @since 21.1.0
     *
     * @param Model $assessment
     */
    public function getLineItemForAssessment(Model $assessment): void
    {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#get-line-item-service-endpoint
        $repository     = $this->createRepository($assessment);
        $handler        = new GetLineItemServiceServerRequestHandler($repository, logger: $this->logger);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI put line item service endpoint
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#updating-a-line-item
     *
     * @api   PUT /lti-platform/line-item/assessment/{assessmentId}
     * @since 26.0
     *
     * @param Model $assessment
     */
    public function putLineItemForAssessment(Model $assessment): void
    {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#update-line-item-service-endpoint
        $repository     = $this->createRepository($assessment);
        $handler        = new UpdateLineItemServiceServerRequestHandler($repository, logger: $this->logger);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI delete line item service endpoint: currently not implemented.
     *
     * @api   DELETE /lti-platform/line-item/assessment/{assessmentId}
     * @since 26.0
     *
     * @param Model $assessment
     */
    public function deleteLineItemForAssessment(Model $assessment): void
    {
        $this->logger->error(
            __METHOD__ . ': not implemented',
            ['assessmentId' => $assessment->getId()],
        );
        throw new NotFound();
    }

    /**
     * Score publish service endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#score-publish-service
     *
     * @api   POST /lti-platform/line-item/assessment/{assessmentId}/scores
     * @since 21.1.0
     *
     * @param Model $assessment
     */
    public function postScorePublishForAssessment(Model $assessment): void
    {
        LogHelper::logControllerAction($this->logger, __METHOD__, $this->request);
        // Line item repository: consists only of a single assessment
        $lineItemRepository = $this->createRepository($assessment);

        // Score repository: consists only of a single assessment, and its scores
        $scoreRepository = new SingleAssessmentScoreRepository(
            $assessment,
            $this->ltiAssessmentMapper,
            $this->ltiAssessmentScoreMapper,
            $this->userCodec,
            $this->logger,
        );

        // Handle the request: this validates the request body
        $handler = new ScoreServiceServerRequestHandler(
            $lineItemRepository,
            $scoreRepository,
            logger: $this->logger,
        );
        $this->response = $this->createResponse($handler);
    }

    /**
     * Create a repository for line items for the given assessment.
     *
     * @param Model $assessment
     * @return LineItemRepositoryInterface
     */
    private function createRepository(Model $assessment): LineItemRepositoryInterface
    {
        return new SingleAssessmentRepository(
            $assessment,
            $this->assessmentDataSource,
            $this->routeGenerator,
            $this->logger,
        );
    }

    /**
     * Create a response for this controller's request.
     *
     * @param LtiServiceServerRequestHandlerInterface $handler The handler for the incoming request.
     * @return ResponseInterface
     * @throws OAuthServerException if the request is invalid.
     */
    private function createResponse(LtiServiceServerRequestHandlerInterface $handler): ResponseInterface
    {
        $requestHandler = new RequestHandler(
            $this->request,
            $this->registrations,
            $this->accessTokenCache,
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
            $this->logger,
        );
        return $requestHandler->handleRequest($handler);
    }
}
