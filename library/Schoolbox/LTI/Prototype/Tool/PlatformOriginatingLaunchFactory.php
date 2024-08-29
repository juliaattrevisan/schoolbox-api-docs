<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Tool;

use Cache\Adapter\PHPArray\ArrayCachePool;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Result\LaunchValidationResultInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Tool\ToolLaunchValidator;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Schoolbox\LTI\Security\Nonce\NonceRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for platform originating launches.
 */
class PlatformOriginatingLaunchFactory
{
    /**
     * Tool launch validator, created in the constructor.
     */
    private ToolLaunchValidator $validator;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrationRepository
     */
    public function __construct(RegistrationRepositoryInterface $registrationRepository)
    {
        $this->validator = new ToolLaunchValidator(
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
        return $this->validator->validatePlatformOriginatingLaunch($request);
    }
}
