<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */


declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Web\Controller\Base as BaseController;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Ags\Service\Score\Server\Handler\ScoreServiceServerRequestHandler;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use Psr\Cache\CacheItemPoolInterface;
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
     * LTI assessment mapper, for retrieving the assessment's details.
     */
    private LtiAssessment\Mapper $ltiAssessmentMapper;

    /**
     * LTI score mapper, for writing a mark for the assessment.
     */
    private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper;

    /**
     * Repository of tool+platform registrations.
     */
    private RegistrationRepositoryInterface $registrations;

    /**
     * User codec, used to decode a user ID in the score publish request into
     * an actual user.
     */
    private UserCodec $userCodec;

    /**
     * Cache for access tokens.
     */
    private CacheItemPoolInterface $accessTokenCache;

    /**
     * Constructor
     *
     * @param LtiAssessment\Mapper            $ltiAssessmentMapper
     * @param LtiAssessmentScore\Mapper       $ltiAssessmentScoreMapper
     * @param RegistrationRepositoryInterface $registrations
     * @param UserCodec                       $userCodec
     * @param CacheItemPoolInterface          $accessTokenCache
     */
    public function __construct(
        LtiAssessment\Mapper $ltiAssessmentMapper,
        LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper,
        RegistrationRepositoryInterface $registrations,
        UserCodec $userCodec,
        CacheItemPoolInterface $accessTokenCache
    ) {
        $this->ltiAssessmentMapper      = $ltiAssessmentMapper;
        $this->ltiAssessmentScoreMapper = $ltiAssessmentScoreMapper;
        $this->registrations            = $registrations;
        $this->userCodec                = $userCodec;
        $this->accessTokenCache         = $accessTokenCache;
    }

    /**
     * LTI get line items endpoint: gets all line items with the given GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#container-request-filters
     *
     * @param string $guid
     *
     * @api   GET /lti-platform/line-item/guid/{guid}
     * @since 25.1
     */
    public function getLineItemsForGuid(string $guid): void
    {
        throw new NotFound();
    }

    /**
     * LTI create line item endpoint: creates a new line item with the given
     * GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#creating-a-new-line-item
     *
     * @param string $guid
     *
     * @api   POST /lti-platform/line-item/guid/{guid}
     * @since 25.1
     */
    public function postLineItemForGuid(string $guid): void
    {
        throw new NotFound();
    }

    /**
     * LTI get line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @param string $guid
     * @param string $id
     * @return void
     */
    public function getLineItemForGuidAndId(string $guid, string $id): void
    {
        throw new NotFound();
    }

    /**
     * LTI update line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @param string $guid
     * @param string $id
     * @return void
     */
    public function putLineItemForGuidAndId(string $guid, string $id): void
    {
        throw new NotFound();
    }

    /**
     * LTI delete line item endpoint.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#line-item-id-rest-endpoint
     *
     * @param string $guid
     * @param string $id
     * @return void
     */
    public function deleteLineItemForGuidAndId(string $guid, string $id): void
    {
        throw new NotFound();
    }

    /**
     * LTI create score endpoint: creates a new score for this line item with
     * the given GUID.
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0#score-publish-service
     *
     * @param string $guid
     *
     * @api   POST /lti-platform/line-item/guid/{guid}/scores
     * @since 25.1
     */
    public function postScorePublishForGuid(string $guid): void
    {
        throw new NotFound();
    }
}
