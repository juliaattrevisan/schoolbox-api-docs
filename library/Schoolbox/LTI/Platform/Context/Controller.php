<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Context;

use Alaress\Web\Controller\Base as BaseController;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use OAT\Library\Lti1p3Nrps\Service\Server\Handler\MembershipServiceServerRequestHandler;
use Schoolbox\Instance;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\LTI\Membership\Builder;
use Schoolbox\LTI\ResourceLink\LaunchFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\LTI\Util\ServerRequestFactory;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\UserMapper;

class Controller extends BaseController
{
    /**
     * LTI 1.3 NPRS endpoint: retrieves this folder's members.
     *
     * A `rlid` (resource link ID) query string parameter may be supplied:
     * if supplied, that corresponds to an LTI assessment's ID, and the unique
     * membership for that assessment will be supplied instead.
     *
     * @api GET /lti-plaform/context/folder/{folderId}/memberships
     * @since 21.1.0
     *
     * @param Model $folder
     */
    public function getMembershipsForFolder(Model $folder): void
    {
        // Build and validate membership request
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-nrps/doc/platform/
        $request = (new ServerRequestFactory())->build($this->request);

        /** @var RegistrationRepositoryInterface $registrationRepository */
        $registrationRepository = $this->getDi()->get('lti.registrations');

        /** @var UserCodec $userCodec */
        $userCodec = $this->getDi()->get('lti.userCodec');

        /** @var AssessmentDataSource $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        /** @var Instance $instance */
        $instance = $this->getDi()->get('instance');

        $builder = new Builder(
            $folder,
            UserAccessMapper::getInstance(),
            $assessmentDataSource,
            $this->getConfigLoader(),
            $userCodec,
            new LaunchFactory(
                $registrationRepository,
                Access::getInstance(),
                $this->router,
                $instance,
                $this->getConfigLoader(),
                new UserCodec(UserMapper::getInstance()),
            ),
        );

        // Access token sanity checker: validates the request token was created by a tool
        $validator = new RequestAccessTokenValidator($registrationRepository);

        $handler = new MembershipServiceServerRequestHandler($builder);

        $server = new LtiServiceServer($validator, $handler);
        $this->populateResponseFromPsr7Response($server->handle($request));
    }
}
