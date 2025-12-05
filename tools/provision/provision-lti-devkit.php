#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Ramsey\Uuid\Uuid;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\LTI\Provider\Mapper;
use Schoolbox\LTI\Provider\Model;
use Schoolbox\Url\Uri;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new class extends App {
        private const OPT_LONG_PUBLIC_KEY = 'key:';
        private const OPT_LONG_HOST = 'host:';
        private const OPT_LONG_PORT = 'port:';

        public function getLongOpts(): array
        {
            return [self::OPT_LONG_PUBLIC_KEY, self::OPT_LONG_PORT, self::OPT_LONG_HOST];
        }

        public function run(): void
        {
            // Create the LTI tool configuration
            $host      = $this->getOpt(self::OPT_LONG_HOST);
            $port      = $this->getOptAsInt(self::OPT_LONG_PORT);
            $publicKey = $this->getOpt(self::OPT_LONG_PUBLIC_KEY);
            if (!isset($host)) {
                $this->getLogger()->error('No host specified: provide with the `--host` flag');
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
            if (!isset($publicKey)) {
                $this->getLogger()->error('No public key specified: provide with the `--key` flag');
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }

            $deploymentId  = Uuid::uuid4()->toString();
            $baseUrl       = new Uri($host)->withPort($port);
            $oidcUrl       = $baseUrl->withPath(rtrim($baseUrl->getPath(), '/') . '/lti1p3/oidc/initiation');
            $launchUrl     = $baseUrl->withPath(rtrim($baseUrl->getPath(), '/') . '/tool/launch');
            $ltiToolMapper = Mapper::getInstance();
            $ltiTool       = $ltiToolMapper->createModel()
                ->setName('Devkit')
                ->setLtiVersion(Model::LTI_VERSION_1_3)
                ->setClientId(null)
                ->setDeploymentId($deploymentId)
                ->setAudience(null)
                ->setOidcInitiationUrl($oidcUrl)
                ->setLaunchUrl($launchUrl)
                ->setDeepLinkingUrl($launchUrl)
                ->setJwksUrl(null)
                ->setPublicKey($publicKey)
                ->setKey(null)
                ->setSecret(null)
                ->setXml(null)
                ->setIcon(null)
                ->setUrl(null)
                ->setCustom(null)
                ->setTarget(Model::TARGET_IFRAME)
                ->setLaunchType(Model::LAUNCH_TYPE_BASIC)
                ->setAvailableForComponent(true)
                ->setAvailableForAssessment(true);
            $ltiToolMapper->save($ltiTool);

            echo "Inserted LTI tool with deployment ID {$deploymentId}\n";
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
