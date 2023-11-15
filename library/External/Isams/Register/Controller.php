<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use Alaress\Web\Controller\Base as BaseController;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use Schoolbox\Url\Uri;
use Schoolbox\User;

/**
 * Controller for the iSams register.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param HttpClient $httpClient for querying iSams
     */
    public function __construct(private HttpClient $httpClient)
    {
    }

    /**
     * Get the register for a student.
     *
     * @api GET /modules/isams/register/student/{studentId}
     * @since 23.1
     *
     * @param User $student
     * @return void
     */
    public function getStudent(User $student): void
    {
        // Hit the iSams API:
        // first, need to exchange client id/secret for an access token
        $apiUrl        = new Uri($this->getConfigLoader()->get('external_isams_rest_api_host'));
        $tokenResponse = $this->httpClient->get(
            $apiUrl
                ->withPath('/auth/connect/token')
                ->appendQueryParameters([
                    'client_id'     => $this->getConfigLoader()->get('external_isams_rest_api_client_id'),
                    'client_secret' => $this->getConfigLoader()->get('external_isams_rest_api_client_secret'),
                ])
        );
        $tokenResponseBody = json_decode($tokenResponse->getBody()->getContents(), true);
        $accessToken       = $tokenResponseBody['access_token'];


        // /Main/api/students/registers/
        $apiResponse = $this->httpClient->get(
            $apiUrl->withPath('/Main/api/students/registers/' . $student->getExternalId()),
            [
                RequestOptions::HEADERS => [
                    'authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );

        var_dump($apiResponse->getBody()->getContents());

        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            ['student' => $student],
        );
    }
}
