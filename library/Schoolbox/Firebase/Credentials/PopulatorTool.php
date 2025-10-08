<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Firebase\Credentials;

use Schoolbox\Mapper\Exception;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\Service;
use Schoolbox\MobileApp\ServiceMapper;

/**
 * Obtains credentials from $credentialsEndpoint,
 * configured to suit your unique use case via
 * generateEndpoint() and buildPayload()
 */
class PopulatorTool extends \Schoolbox\InstanceRegistry\Credentials\PopulatorTool
{
    /**
     * These are the credentials we need; purely cosmetic
     *
     * @var string
     */
    protected string $name = 'Firebase';


    /**
     * This endpoint gets us learnosity credentials specially
     */
    protected string $credentialsEndpoint = '%s/api/secret/firebase/%s';

    /**
     * Configure the url we use to obtain credentials
     *
     * @param string $irUrl
     * @param string $environment
     * @return string
     */
    protected function generateEndpoint(string $irUrl, string $environment): string
    {
        return sprintf(
            $this->credentialsEndpoint,
            $irUrl,
            $environment
        );
    }


    /**
     * Re-map that again into something easily understood,
     * trimming values in case someone copypasted whitespaces/control chars for some reason
     */
    protected function buildPayload(array $content): array
    {
        // sanitise the data, we need to convert from \n to the actual character, so the open_ssl keys still work
        $key = stripcslashes(json_encode($content['key'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_UNICODE));
        $public_configuration = stripcslashes(json_encode($content['public_configuration'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_UNICODE));

        /* These go into mobile_app
         */
        return [
            'app_identifier'        => trim((string)$content['app_identifier']),
            'key'                   => $key,
            'public_configuration'  => $public_configuration,
            'web_push_public_key'   => trim((string)$content['web_push_public_key'])
        ];
    }

    protected function updateConfig(array $configTuples): void
    {
        $this->getLogger()->info("Updating instance's config table with fetched credentials...");

        $mobile = ServiceMapper::getInstance();
        try {
            $firebaseApp = $mobile->findOneByColumnList([
                'app_identifier' => $configTuples['app_identifier'],
                'provider' => 'Firebase'
            ]);
            $this->getLogger()->debug('Found Entry, Updating');
        } catch (Exception\Deleted $e) {
            /** @var Service $firebaseApp */
            $firebaseApp = $e->getDeletedObject();
            $firebaseApp->setDeletedAt(null);
            $this->getLogger()->debug('Found Deleted Entry, UnDeleting and Updating');
        } catch (Exception $e) {
            $firebaseApp = $mobile->createModel();
            $this->getLogger()->debug('No Entry Found, Creating');
        }
        /**
         * update to the IR creds
         * app_identifier, provider, `key`, public_configuration, web_push_public_key
         */
        /** @var Service $firebaseApp */
        $firebaseApp->setAppIdentifier($configTuples['app_identifier'])
            ->setProviderName('Firebase')
            ->setKey($configTuples['key'])
            ->setPublicConfiguration($configTuples['public_configuration'])
            ->setWebPushPublicKey($configTuples['web_push_public_key']);


        $mobile->save($firebaseApp);

        $this->getLogger()->debug('Saved');
    }
}
