<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use Alaress\Web\Controller\Base as BaseController;
use Alaress_Date_Format;
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
        $authHeader        = [
            'authorization' => 'Bearer ' . $accessToken,
        ];

        // Request 1: find registers
        // TODO these should be configured:
        //      also a mapping from codes to IDs should be cached
        $apiResponse = $this->httpClient->get(
            $apiUrl->withPath('/Main/api/registers/'),
            [RequestOptions::HEADERS => $authHeader,],
        );
        $apiResponseBody = json_decode($apiResponse->getBody()->getContents(), true);
        $registers       = $apiResponseBody['registers'];
        $globalData      = [];
        $studentData     = [];
        foreach ($registers as $register) {
            $registerId               = $register['id'];
            $globalData[$registerId]  = $register;
            $studentData[$registerId] = [];

//            // Alternate request: find student register, filtered to $student:
//            // This _does_ return filtered results, but doesn't include date
//            // ranges or metadata
//            try {
//                $registerResponse = $this->httpClient->get(
//                    $apiUrl
//                        ->appendQueryParameters([
//                            '$filter' => "SchoolId eq '{$student->getExternalId()}'"
//                        ])
//                        ->withPath('/Main/api/students/registers/' . $registerId),
//                    [RequestOptions::HEADERS => $authHeader,],
//                );
//                $registerResponseBody = json_decode($registerResponse->getBody()->getContents(), true);
//            } catch (ClientExceptionInterface $e) {
//                // No record exists for this student: that's okay
//                $registerResponseBody = [];
//            }
//            new \Alaress_Probe($registerResponseBody);

            // Request 1.1: find metadata for fields
            // This ought to be cacheable for a lot longer than student data
            try {
                $metadataResponse = $this->httpClient->get(
                    $apiUrl->withPath('/Main/api/registers/' . $registerId . '/metadata'),
                    [RequestOptions::HEADERS => $authHeader,],
                );
                $metadataResponseBody = json_decode($metadataResponse->getBody()->getContents(), true);
            } catch (ClientExceptionInterface $e) {
                // No metadata exists for this field: that's okay
                $metadataResponseBody = [];
            }

            // Request 1.1.1: field data sources
            foreach (($metadataResponseBody['fields'] ?? []) as $metadataField) {
                // Fetch data source only if it's a list
                $metadataFieldValues = null;
                $dataSource          = strtolower($metadataField['properties']['dataSource'] ?? '');
                if (in_array($dataSource, ['private', 'shared'])) {
                    if ($metadataField['properties']['dataSourceUrl'] ?? null) {
                        try {
                            $dataSourceResponse = $this->httpClient->get(
                                $metadataField['properties']['dataSourceUrl'],
                                [RequestOptions::HEADERS => $authHeader,],
                            );
                            $dataSourceResponseBody = json_decode($dataSourceResponse->getBody()->getContents(), true);
                        } catch (ClientExceptionInterface $e) {
                            // No metadata exists for this field: that's okay
                            $dataSourceResponseBody = [];
                        }
                        foreach (($dataSourceResponseBody['items'] ?? []) as $item) {
                            $metadataFieldValues[$item['value']] = $item['text'];
                        }
                    }
                }
                $globalData[$registerId]['metadata'][strtolower($metadataField['key'])] = [
                    'name'   => $metadataField['name'],
                    'values' => $metadataFieldValues,
                ];
            }

            // Request 1.2: find date ranges for student
            try {
                $dateRangesResponse = $this->httpClient->get(
                    $apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $student->getExternalId() . '/dateranges'),
                    [RequestOptions::HEADERS => $authHeader,],
                );
                $dateRangesResponseBody = json_decode($dateRangesResponse->getBody()->getContents(), true);
            } catch (ClientExceptionInterface $e) {
                // No record exists for this student: that's okay
                $dateRangesResponseBody = [];
            }

            // Request 1.3: find fields for student
            try {
                $fieldsResponse = $this->httpClient->get(
                    $apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $student->getExternalId() . '/fields'),
                    [RequestOptions::HEADERS => $authHeader,],
                );
                $fieldsResponseBody = json_decode($fieldsResponse->getBody()->getContents(), true);
            } catch (ClientExceptionInterface $e) {
                // No record exists for this student: that's okay
                $fieldsResponseBody = [];
            }

            // Collate student data
            foreach (($fieldsResponseBody['fields'] ?? []) as $key => $fieldValue) {
                $key = strtolower($key);
                if (isset($fieldValue)) {
                    $studentData[$registerId]['fields'][$key] = [
                        'name'  => $globalData[$registerId]['metadata'][$key]['name'],
                        'value' => $globalData[$registerId]['metadata'][$key]['values'][$fieldValue] ?? $fieldValue,
                    ];
                }
            }
            foreach (($dateRangesResponseBody['dateRanges'] ?? []) as $dateRange) {
                $studentData[$registerId]['dateRanges'][] = [
                    'from' => new Alaress_Date_Format($dateRange['startDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
                    'to'   => new Alaress_Date_Format($dateRange['endDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ];
            }
        }

        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            [
                'student'     => $student,
                'studentData' => $studentData,
            ],
        );
    }
}
