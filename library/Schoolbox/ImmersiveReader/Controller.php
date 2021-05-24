<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ImmersiveReader;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Proxy\Server as ProxyServer;

class Controller extends Base
{
    /**
     * This function does an oAuth request to Azure and gets back a token which it provides to the user via JSON
     * This enables the user to then use the Immersive Reader service with a short lived token.
     * This is done on the backend to protect the server secret from the end user.
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getTokenAndSubdomain()
    {
        $tenant_id = $this->getConfigLoader()->get('office365_tenant_id');
        $client_id = $this->getConfigLoader()->get('office365_client');
        $client_secret = $this->getConfigLoader()->get('office365_secret');
        $subdomain = $this->getConfigLoader()->get('office365_immersive_reader_name');

        $httpClient = new Client(['curl' => ProxyServer::getInstance()->buildCurlOptions()]);

        try {
            $result = $httpClient->post(
                'https://login.windows.net/' . $tenant_id . '/oauth2/token',
                [
                    'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'form_params' => [
                        'client_id'     => $client_id,
                        'client_secret' => $client_secret,
                        'resource'      => 'https://cognitiveservices.azure.com/',
                        'grant_type'    => 'client_credentials',
                    ],
                ]
            );

            $authToken = json_decode($result->getBody()->getContents(), true);

            if ($authToken['access_token']) {
                $output = ['token' => $authToken['access_token'], 'subdomain' => $subdomain];
                $this->getResponse()->content->set(
                    $this->buildJsonOutput($output)
                );
            }
        } catch (ClientException $e) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->getResponse()->content->set(
                $this->buildJsonOutput($problem->asArray())
            );
        }
    }
}
