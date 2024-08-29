<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Platform;

use Cache\Adapter\PHPArray\ArrayCachePool;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Platform\PlatformLaunchValidator;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Result\LaunchValidationResultInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\LTI\Security\Nonce\NonceRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for tool originating launches, e.g. a response from a deep linking
 * request.
 */
class ToolOriginatingLaunchFactory
{
    /**
     * Tool launch validator, created in the constructor.
     */
    private PlatformLaunchValidator $validator;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrationRepository
     */
    public function __construct(RegistrationRepositoryInterface $registrationRepository)
    {
        $this->validator = new PlatformLaunchValidator(
            $registrationRepository,
            new NonceRepository(new ArrayCachePool())
        );
    }

    /**
     * Build the launch.
     *
     * @param ServerRequestInterface $request
     * @return LaunchValidationResultInterface
     */
    public function build(ServerRequestInterface $request): LaunchValidationResultInterface
    {
        return $this->validator->validateToolOriginatingLaunch($request);
    }
}
