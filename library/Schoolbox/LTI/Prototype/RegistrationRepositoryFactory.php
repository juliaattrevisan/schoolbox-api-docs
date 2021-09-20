<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Factory;

/**
 * Factory for the registration repository for the test platform and tool.
 */
class RegistrationRepositoryFactory
{
    /**
     * Configuration: required to build a base URL for the repository.
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Build the repository.
     *
     * @return RegistrationRepository
     */
    public function build(): RegistrationRepository
    {
        return new RegistrationRepository(
            (new Factory($this->config))->buildFullyQualified()->setScheme('https'),
            $this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
            $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY)
        );
    }
}
