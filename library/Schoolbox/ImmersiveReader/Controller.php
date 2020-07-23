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

class Controller extends Base
{

    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $subdomain;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * Controller constructor.
     **/
    public function __construct()
    {
       $this->httpClient = new Client();

       $this->tenant_id = $this->getConfigLoader()->get('office365_tenant_id');
       $this->client_id = $this->getConfigLoader()->get('office365_client');
       $this->client_secret = $this->getConfigLoader()->get('office365_secret');
       $this->subdomain = $this->getConfigLoader()->get('office365_immersive_reader_name');

    }

    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getTokenAndSubdomain()
    {
        try {
            $result = $this->httpClient->post('https://login.windows.net/' . $this->tenant_id . '/oauth2/token',
                [
                    'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'form_params' => [
                        'client_id'     => $this->client_id,
                        'client_secret' => $this->client_secret,
                        'resource'      => 'https://cognitiveservices.azure.com/',
                        'grant_type'    => 'client_credentials',
                    ],
                ]
            );
        } catch (ClientException $e ) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->getResponse()->content->set(
                $this->buildJsonOutput($problem->asArray())
            );
        }

        $authToken = json_decode($result->getBody()->getContents());

        if($authToken->access_token) {
            $output = ['token'=>$authToken->access_token, 'subdomain' => $this->subdomain];
            $this->getResponse()->content->set(
                $this->buildJsonOutput($output)
            );
        }
    }
}
