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
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Url\Uri;
use Schoolbox\User;

/**
 * Controller for the iSams register.
 */
class Controller extends BaseController
{
    private CacheHelper $cacheHelper;

    /**
     * Constructor
     *
     * @param HttpClient $httpClient for querying iSams
     * @param CacheInterface $cache for caching the api response
     */
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache)
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
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
        // first, need to exchange client id/secret for an access token
        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        $allowedRegisters = explode(',', $this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_STUDENT_REGISTERS) ?? '');

        $registers       = $this->cacheHelper->getRegisters();
        $globalData      = [];
        $studentData     = [];
        foreach ($registers as $register) {
            if (!in_array($register['code'], $allowedRegisters)) {
                // not a filtered register Ignoring...
                continue;
            }
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

            // Request 1.1.1: field data sources
            foreach ($this->cacheHelper->getMetadataFields($registerId) as $metadataField) {
                // Fetch data source only if it's a list
                $metadataFieldValues = null;
                $dataSource          = strtolower($metadataField['properties']['dataSource'] ?? '');
                if (in_array($dataSource, ['private', 'shared'])) {
                    if ($metadataField['properties']['dataSourceUrl'] ?? null) {
                        foreach ($this->cacheHelper->getDataSource($metadataField['properties']['dataSourceUrl']) as $item) {
                            $metadataFieldValues[$item['value']] = $item['text'];
                        }
                    }
                }
                $globalData[$registerId]['metadata'][strtolower($metadataField['key'])] = [
                    'name'   => $metadataField['name'],
                    'values' => $metadataFieldValues,
                ];
            }

            // Collate student data
            foreach ($this->cacheHelper->getStudentFields($registerId, $student->getExternalId()) as $key => $fieldValue) {
                $key = strtolower($key);
                if (isset($fieldValue)) {
                    $studentData[$registerId]['fields'][$key] = [
                        'name'  => $globalData[$registerId]['metadata'][$key]['name'],
                        'value' => $globalData[$registerId]['metadata'][$key]['values'][$fieldValue] ?? $fieldValue,
                    ];
                }
            }
            foreach ($this->cacheHelper->getStudentDateRanges($registerId, $student->getExternalId()) as $dateRange) {
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

    public function getRegisterList()
    {
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));

        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        new \Alaress_Probe($this->cacheHelper->getRegisters());
    }
}
