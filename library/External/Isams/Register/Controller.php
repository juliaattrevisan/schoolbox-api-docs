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
use Psr\Http\Client\ClientExceptionInterface;
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
        $tokenResponse = $this->httpClient->post(
            $apiUrl->withPath('/auth/connect/token'),
            [
                RequestOptions::FORM_PARAMS => [
                    'client_id'     => $this->getConfigLoader()->get('external_isams_rest_api_client_id'),
                    'client_secret' => $this->getConfigLoader()->get('external_isams_rest_api_client_secret'),
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'restapi',
                ]
            ]
        );
        $tokenResponseBody = json_decode($tokenResponse->getBody()->getContents(), true);
        $accessToken       = $tokenResponseBody['access_token'];

        // Find registers?
        // TODO these should be configured:
        //      also a mapping from codes to IDs should be cached
        $apiResponse = $this->httpClient->get(
//            $apiUrl->withPath('/Main/api/students/' . $student->getExternalId()),
            $apiUrl->withPath('/Main/api/registers/'),
            [
                RequestOptions::HEADERS => [
                    'authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );
        $apiResponseBody = json_decode($apiResponse->getBody()->getContents(), true);
        $registerIds     = array_column($apiResponseBody['registers'], 'id');
        foreach ($registerIds as $registerId) {
            try {
                $apiResponse = $this->httpClient->get(
//                    $apiUrl->withPath('/Main/api/students/registers/' . $registerId),
                    $apiUrl->withPath('/Main/api/registers/' . $registerId . '/recordCollections/'),
//                    $apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $student->getExternalId() . '/fields'),
                    [
                        RequestOptions::HEADERS => [
                            'authorization' => 'Bearer ' . $accessToken,
                        ]
                    ]
                );
            } catch (ClientExceptionInterface $e) {
                // No record exists for this student: that's okay
                continue;
            }
            $apiResponseBody     = json_decode($apiResponse->getBody()->getContents(), true);
            $recordCollectionIds = array_column($apiResponseBody['recordCollections'], 'id');
            foreach ($recordCollectionIds as $recordCollectionId) {
                try {
                    $apiResponse = $this->httpClient->get(
                        $apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $student->getExternalId() . '/recordCollections/'. $recordCollectionId . '/records'),
                        [
                            RequestOptions::HEADERS => [
                                'authorization' => 'Bearer ' . $accessToken,
                            ]
                        ]
                    );
                } catch (ClientExceptionInterface $e) {
                    // No record exists for this student: that's okay
                    continue;
                }
                $apiResponseBody     = json_decode($apiResponse->getBody()->getContents(), true);
                new \Alaress_Probe($apiResponseBody);
            }
        }

        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            ['student' => $student],
        );
    }
}
