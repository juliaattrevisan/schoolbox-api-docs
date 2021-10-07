<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\Url\Factory;

/**
 * Factory for the registration repository for the prototype platform and tool.
 */
class RegistrationRepositoryFactory
{
    /**
     * Configuration: required to build a base URL for the repository.
     */
    private IConfigLoader $config;

    /**
     * Mapper for LTI tools.
     */
    private ToolMapper $toolMapper;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param ToolMapper    $toolMapper
     */
    public function __construct(IConfigLoader $config, ToolMapper $toolMapper)
    {
        $this->config     = $config;
        $this->toolMapper = $toolMapper;
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
            $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
            $this->toolMapper->findByColumn('lti_version', ToolModel::LTI_VERSION_1_3)
        );
    }
}
