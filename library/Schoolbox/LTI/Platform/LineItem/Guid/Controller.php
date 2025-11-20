<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */


declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\CreateLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\DeleteLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\GetLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\ListLineItemsServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\UpdateLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\Handler\LtiServiceServerRequestHandlerInterface;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\LineItem as LtiAssessmentLineItem;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\LineItem\GuidRepository;
use Schoolbox\LTI\Score\LineItemGuidScoreRepository;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Url\Factory;

/**
 * LTI Platform controller for line items.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param LtiAssessmentLineItem\Mapper    $lineItemMapper           Line item mapper, for fetching line items.
     * @param LtiAssessment\Mapper            $ltiAssessmentMapper      LTI assessment mapper, for retrieving the
     *                                                                  assessment's details.
     * @param LtiAssessmentScore\Mapper       $ltiAssessmentScoreMapper LTI score mapper, for writing a mark for the
     *                                                                  assessment.
     * @param MapperProxy                     $assessmentDataSource     Assessment data source, for fetching the
     *                                                                  assessment for a line item when posting scores.
     * @param RegistrationRepositoryInterface $registrations            Repository of tool+platform registrations.
     * @param UserCodec                       $userCodec                User codec, used to decode a user ID in the
     *                                                                  score publish request into an actual user.
     * @param CacheItemPoolInterface          $accessTokenCache         Cache for access tokens.
     */
    public function __construct(
        private LtiAssessment\LineItem\Mapper $lineItemMapper,
        private LtiAssessment\Mapper $ltiAssessmentMapper,
        private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        private MapperProxy $assessmentDataSource,
        private RegistrationRepositoryInterface $registrations,
        private UserCodec $userCodec,
        private CacheItemPoolInterface $accessTokenCache
    ) {
    }

    /**
     * LTI get line items endpoint: gets all line items with the given GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#container-request-filters
     *
     * @api   GET /lti-platform/line-item/guid/{guid}
     * @since 25.1
     *
     * @param string $guid
     * @return void
     */
    public function getLineItemsForGuid(string $guid): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#list-line-items-service-endpoint
        $repository     = $this->createRepository($guid);
        $handler        = new ListLineItemsServiceServerRequestHandler($repository);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI create line item endpoint: creates a new line item with the given
     * GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#creating-a-new-line-item
     *
     * @api   POST /lti-platform/line-item/guid/{guid}
     * @since 25.1
     *
     * @param string $guid
     * @return void
     */
    public function postLineItemForGuid(string $guid): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#create-line-item-service-endpoint
        $repository     = $this->createRepository($guid);
        $handler        = new CreateLineItemServiceServerRequestHandler($repository);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI get line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   GET /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string $guid
     * @return void
     */
    public function getLineItemForGuidAndLineItem(string $guid): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#get-line-item-service-endpoint
        // Note that the line item is identified by the URL itself
        $repository     = $this->createRepository($guid);
        $handler        = new GetLineItemServiceServerRequestHandler($repository);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI update line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   PUT /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string $guid
     * @return void
     */
    public function putLineItemForGuidAndLineItem(string $guid): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#update-line-item-service-endpoint
        $repository     = $this->createRepository($guid);
        $handler        = new UpdateLineItemServiceServerRequestHandler($repository);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI delete line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   DELETE /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string $guid
     * @return void
     */
    public function deleteLineItemForGuidAndLineItem(string $guid): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#delete-line-item-service-endpoint
        $repository     = $this->createRepository($guid);
        $handler        = new DeleteLineItemServiceServerRequestHandler($repository);
        $this->response = $this->createResponse($handler);
    }

    /**
     * LTI create score endpoint: creates a new score for this line item with
     * the given GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#score-publish-service
     *
     * @api   POST /lti-platform/line-item/guid/{guid}/{id}/scores
     * @since 25.1
     *
     * @param string                       $guid
     * @param LtiAssessment\LineItem\Model $lineItem
     */
    public function postScorePublishForGuidAndLineItem(string $guid, LtiAssessment\LineItem\Model $lineItem): void
    {
        // Need to load domain object for LTI assessment:
        // must also assert that the line item matches.
        // Do that here I guess?
        $ltiAssessment = $this->assessmentDataSource->findOneByColumn('ltiAssessment.guid', $guid);
        if (!$ltiAssessment instanceof \Schoolbox\Learning\Assessment\Lti\Model) {
            // Not an LTI assessment: this is a user error
            throw new NotFound();
        }

        // Assert the line item's assessment is the same as the GUID's
        if ($ltiAssessment->getId() !== $lineItem->getLtiAssessment()->getId()) {
            throw new NotFound();
        }

        // If assertions pass, attempt the request:
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/score/platform.md
        $handler = new ScoreServiceServerRequestHandler(
            $this->createRepository($guid),
            new LineItemGuidScoreRepository(
                $ltiAssessment,
                $lineItem,
                $this->ltiAssessmentMapper,
                $this->ltiAssessmentScoreMapper,
                $this->userCodec,
            )
        );
        $this->response = $this->createResponse($handler);
    }

    /**
     * Create a repository for line items with the given GUID.
     *
     * @param string $guid
     * @return LineItemRepositoryInterface
     */
    private function createRepository(string $guid): LineItemRepositoryInterface
    {
        return new GuidRepository(
            $guid,
            $this->lineItemMapper,
            $this->routeGenerator,
            new Factory($this->getConfigLoader())->buildFullyQualified(),
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
        // Validate access token
        $validator      = new RequestAccessTokenValidator($this->registrations);
        $resourceServer = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );

        // Handle the request
        return new LtiServiceServer($validator, $handler)
            ->handle($resourceServer->validateAuthenticatedRequest($this->request));
    }
}
