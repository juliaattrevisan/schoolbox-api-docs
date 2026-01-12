<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\LineItem\SingleAssessmentRepository;
use Schoolbox\LTI\Platform\Server\ServiceServer;
use Schoolbox\LTI\Score\SingleAssessmentScoreRepository;
use Schoolbox\LTI\User\Codec as UserCodec;

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
     * @param RegistrationRepositoryInterface $registrations            Repository of tool+platform registrations.
     * @param UserCodec                       $userCodec                User codec, used to decode a user ID in the
     *                                                                  score publish request into an actual user.
     * @param CacheItemPoolInterface          $accessTokenCache         Cache for access tokens.
     * @param LoggerInterface                 $logger                   Logger for what this controller does.
     */
    public function __construct(
        private LtiAssessment\Mapper $ltiAssessmentMapper,
        private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        private RegistrationRepositoryInterface $registrations,
        private UserCodec $userCodec,
        private CacheItemPoolInterface $accessTokenCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * LTI line item service endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   GET /lti-platform/line-item/assessment/{assessmentId}
     * @since 21.1.0
     *
     * @param Model $assessment
     */
    public function getLineItemForAssessment(Model $assessment): void
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
        // Build and validate score publish request
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-ags/doc/score/platform/

        // Access token sanity checker:
        // validates the request token was created by a tool
        $validator = new RequestAccessTokenValidator($this->registrations, $this->logger);

        // Access token validity checker
        // TODO move this into \Schoolbox\Application\WebRequest\Kernel::extractUserFromAuthHeader
        $resourceServerValidator = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache, $this->logger),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );
        $request = $resourceServerValidator->validateAuthenticatedRequest($this->request);

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
        $server         = new ServiceServer($validator, $handler, logger: $this->logger);
        $this->response = $server->handle($request);
    }

    /**
     * Create a repository for line items for the given assessment.
     *
     * @param Model $assessment
     * @return LineItemRepositoryInterface
     */
    private function createRepository(Model $assessment): LineItemRepositoryInterface
    {
        return new SingleAssessmentRepository($assessment, $this->routeGenerator, $this->logger);
    }
}
