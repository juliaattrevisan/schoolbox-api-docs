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
        private const OPT_SHORT_HELP = 'h';
        private const OPT_LONG_HELP = 'help';
        private const OPT_LONG_PUBLIC_KEY = 'key:';
        private const OPT_LONG_HOST = 'host:';

        /**
         * @inheritdoc
         */
        public function getShortOpts(): array
        {
            return [self::OPT_SHORT_HELP];
        }

        /**
         * @inheritdoc
         */
        public function getLongOpts(): array
        {
            return [
                self::OPT_LONG_HELP,
                self::OPT_LONG_PUBLIC_KEY,
                self::OPT_LONG_HOST,
            ];
        }

        public function run(): void
        {
            if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
                echo <<<HELP
NAME
    create-devkit-lti-tool.php - create a LTI tool configuration for use with the LTI Devkit

SYNOPSIS
    create-devkit-lti-tool.php [--host HOST] [--key KEY]

DESCRIPTION
    This script is used to create an LTI tool configuration for use with the LTI Devkit. Once finished, outputs the
    deployment ID of the tool.

    Also enables LTI assessments, LTI debug mode and the 3rd party assessment homepage component.

    It should only be used for development purposes.

OPTIONS
    -h, --help              Print this help text and exit
    --host                  The host the devkit is running on. May include a port number.
    --key                   The devkit's public key: may be found in its `config/keys/public.key` file.
HELP;
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }

            // Create the LTI tool configuration
            $host      = $this->getOpt(self::OPT_LONG_HOST);
            $publicKey = $this->getOpt(self::OPT_LONG_PUBLIC_KEY);
            if (!isset($host)) {
                $this->getLogger()->error('No host specified: provide with the `--host` flag');
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
            if (!isset($publicKey)) {
                $this->getLogger()->error('No public key specified: provide with the `--key` flag');
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }

            $this->getLogger()->info('Creating LTI tool configuration');
            $deploymentId  = Uuid::uuid4()->toString();
            $baseUrl       = new Uri($host);
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

            // Configure Schoolbox to allow LTI tool use
            $this->getLogger()->info('Enabling LTI assessments and LTI debug mode');
            $this->getDatabaseInternal()->execute("
                UPDATE config
                SET value = '1'
                WHERE name IN ('lti_assessments_enable', 'lti_debug_mode')
            ");

            $this->getLogger()->info('Enabling 3rd party assessment homepage component');
            $this->getDatabaseInternal()->execute("
                UPDATE component_definition
                SET visible = 'true'
                WHERE class = 'Schoolbox\\\\Learning\\\\Assessment\\\\Lti\\\\Component\\\\Homepage\\\\Controller'
            ");

            printf("%s\n", $deploymentId);
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
