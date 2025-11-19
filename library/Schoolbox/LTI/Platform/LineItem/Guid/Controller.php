<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */


declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use OAT\Library\Lti1p3Ags\Service\LineItem\Server\Handler\CreateLineItemServiceServerRequestHandler;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\LineItem;
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
     * @param LineItem\Mapper                 $lineItemMapper   Line item mapper, for persisting and fetching line items.
     * @param RegistrationRepositoryInterface $registrations    Repository of tool+platform registrations.
     * @param UserCodec                       $userCodec        User codec, used to decode a user ID in the score
     *                                                          publish request into an actual user.
     * @param CacheItemPoolInterface          $accessTokenCache Cache for access tokens.
     */
    public function __construct(
        private LineItem\Mapper $lineItemMapper,
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
        throw new NotFound();
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
        $repository = new Repository(
            $guid,
            $this->lineItemMapper,
            $this->routeGenerator,
            $this->getRouter()->getMatcher()
        );

        $validator = new RequestAccessTokenValidator($this->registrations);

        // Access token validity checker
        // TODO move this into \Schoolbox\Application\WebRequest\Kernel::extractUserFromAuthHeader
        $resourceServerValidator = new ResourceServer(
            new AccessTokenRepository($this->accessTokenCache),
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
        );
        $request = $resourceServerValidator->validateAuthenticatedRequest($this->request);

        $handler = new CreateLineItemServiceServerRequestHandler($repository);

        $server = new LtiServiceServer($validator, $handler);

        // Generates an authenticated response containing the line item creation response
        $this->response = $server->handle($request);
    }

    /**
     * LTI get line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   GET /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string         $guid
     * @param LineItem\Model $lineItem
     * @return void
     */
    public function getLineItemForGuidAndLineItem(string $guid, LineItem\Model $lineItem): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#get-line-item-service-endpoint
        throw new NotFound();
    }

    /**
     * LTI update line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   PUT /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string         $guid
     * @param LineItem\Model $lineItem
     * @return void
     */
    public function putLineItemForGuidAndLineItem(string $guid, LineItem\Model $lineItem): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#update-line-item-service-endpoint
        throw new NotFound();
    }

    /**
     * LTI delete line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @api   DELETE /lti-platform/line-item/guid/{guid}/{id}
     * @since 25.1
     *
     * @param string         $guid
     * @param LineItem\Model $lineItem
     * @return void
     */
    public function deleteLineItemForGuidAndLineItem(string $guid, LineItem\Model $lineItem): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/lineitem/platform.md#delete-line-item-service-endpoint
        throw new NotFound();
    }

    /**
     * LTI create score endpoint: creates a new score for this line item with
     * the given GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#score-publish-service
     *
     * @api   POST /lti-platform/line-item/guid/{guid}/{id}/scores
     * @since 25.1
     *
     * @param string         $guid
     * @param LineItem\Model $lineItem
     */
    public function postScorePublishForGuidAndLineItem(string $guid, LineItem\Model $lineItem): void
    {
        // https://github.com/oat-sa/lib-lti1p3-ags/blob/main/doc/score/platform.md
        throw new NotFound();
    }
}
