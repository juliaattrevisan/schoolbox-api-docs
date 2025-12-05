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
use Schoolbox\Url\Uri;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new class extends App {
        private const OPT_LONG_PUBLIC_KEY = 'key:';
        private const OPT_LONG_PORT = 'port:';

        public function getLongOpts(): array
        {
            return [self::OPT_LONG_PUBLIC_KEY];
        }

        public function run(): void
        {
            $insertLtiToolQuery = $this->getDatabaseInternal()->prepare("
                INSERT INTO lti_provider (
                    name,
                    lti_version,
                    client_id,
                    deployment_id,
                    audience,
                    oidc_initiation_url,
                    launch_url,
                    deep_linking_url,
                    jwks_url,
                    public_key,
                    `key`,
                    secret,
                    xml,
                    icon,
                    url,
                    custom,
                    target,
                    created_at,
                    updated_at,
                    deleted_at,
                    launch_type,
                    available_for_component,
                    available_for_assessment
                )
                VALUES (
                    'Devkit',
                    '1.3',
                    null,
                    :deploymentId,
                    null,
                    :oidcInitiationUrl,
                    :launchUrl,
                    :launchUrl,
                    '',
                    :publicKey,
                    '',
                    '',
                    '',
                    '',
                    '',
                    null,
                    'iframe',
                    NOW(),
                    NOW(),
                    null,
                    'LTI_LAUNCH_TYPE_BASIC',
                    1,
                    1
                )
            ");
            $deploymentId = Uuid::uuid4()->toString();
            $baseUrl      = new Uri('http://devkit-lti1p3.localhost')->withPort($this->getOptAsInt(self::OPT_LONG_PORT));
            $params       = [
                'deploymentId'      => $deploymentId,
                'oidcInitiationUrl' => $baseUrl->withPath('/lti1p3/oidc/initiation')->__toString(),
                'launchUrl'         => $baseUrl->withPath('/tool/launch')->__toString(),
                'publicKey'         => $this->getOpt(self::OPT_LONG_PUBLIC_KEY),
            ];
            $insertLtiToolQuery->execute($params);
            echo "Inserted LTI tool with deployment ID {$deploymentId}\n";
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
