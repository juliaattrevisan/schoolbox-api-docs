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
use Schoolbox\Application\Exception\NotFound;
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
     * @api GET /modules/isams/register/{register}/student/{studentId}
     * @since 23.1
     *
     * @param string $registerCode
     * @param User $student
     * @return void
     */
    public function getStudent(string $registerCode, User $student): void
    {
        // Hit the iSams API:
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
        // first, need to exchange client id/secret for an access token
        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        /**
         * register1 => [
         *  field1
         *  field2
         * ]
         */
        $allowedRegisters = [];
        $registersConfig = [];
        /**
         * allowed registers is a multi dimentional csv
         *
         * Example:
         * register1,field1,field2,field6
         * register2,field1
         * register6,field7,field12
         */
        $config = $this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_STUDENT_REGISTERS) ?? '';
        if ('' !== $config) {
            // explode to find how many registers we will be working with...
            foreach (preg_split('/\R/', strtolower($config)) as $registerConfig) {
                // explode again to extract the registers, and their fields
                // first object entry is the register, later items are the allowed fields
                $tmp = explode(',', $registerConfig);
                $registerTitle = array_shift($tmp);
                $registersConfig[$registerTitle] = $tmp;
                $allowedRegisters[] = $registerTitle;
            }
        }

        $registers       = $this->cacheHelper->getRegisters();
        $studentData     = [];
        foreach ($registers as $register) {

            /**
             * if the requested register is not in the allowed registers
             * OR
             * if the requested register is not in the current register
             * then continue onto the next register
             *
             * we will either only display 1 register, or throw NotFound.
             */
            if (!in_array(strtolower($register['code']), $allowedRegisters) || strtolower($register['code']) !== strtolower($registerCode)) {
                // the requested register Ignoring...
                continue;
            }

            $studentData[] = [
                'user' => $student,
                'data' => $this->getDataFromRegister($student, $register, $registersConfig),
            ];
            $this->response = $this->responseFromTemplate(
                '@isamsRegister/student.twig',
                [
                    'studentData'   => $studentData, // need an array of students, dummy this here
                ],
            );
            return;
        }
        /**
         * Unable to find requested register...
         */
        throw new NotFound();
    }

    /**
     * Part 2: ISAMS: Financial Registers
     * Only parents can access this
     * it will show their children and the requested 'admin approved' financial register
     *
     * @api GET /modules/isams/register/{register}/parent
     * @since 23.1
     *
     * @param string $registerCode
     * @return void
     */
    public function getParent(string $registerCode)
    {
        // check if we have a valid register code
        // Hit the iSams API:
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
        // first, need to exchange client id/secret for an access token
        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        /**
         * register1 => [
         *  field1
         *  field2
         * ]
         */
        $allowedRegisters = [];
        $registersConfig = [];
        /**
         * allowed registers is a multi dimentional csv
         *
         * Example:
         * register1,field1,field2,field6
         * register2,field1
         * register6,field7,field12
         */
        $config = $this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_FINANCIAL_REGISTERS) ?? '';
        if ('' !== $config) {
            // explode to find how many registers we will be working with...
            foreach (preg_split('/\R/', strtolower($config)) as $registerConfig) {
                // explode again to extract the registers, and their fields
                // first object entry is the register, later items are the allowed fields
                $tmp = explode(',', $registerConfig);
                $registerTitle = array_shift($tmp);
                $registersConfig[$registerTitle] = $tmp;
                $allowedRegisters[] = $registerTitle;
            }
        }

        $registers       = $this->cacheHelper->getRegisters();
        $studentData     = [];
        foreach ($registers as $register) {
            /**
             * if the requested register is not in the allowed registers
             * OR
             * if the requested register is not in the current register
             * then continue onto the next register
             *
             * we will either only display 1 register, or throw NotFound.
             */
            if (!in_array(strtolower($register['code']), $allowedRegisters) || strtolower($register['code']) !== strtolower($registerCode)) {
                // the requested register Ignoring...
                continue;
            }

            // Get each student
            foreach ($this->getUser()->getChildren() as $child) {
                // for each student get the data from that register
                $studentData[] = [
                    'user' => $child,
                    'data' => $this->getDataFromRegister($child, $register, $registersConfig),
                ];
            }

            // display to the parent
            $this->response = $this->responseFromTemplate(
                '@isamsRegister/student.twig',
                [
                    'studentData'   => $studentData
                ],
            );
            return;
        }
        /**
         * Unable to find requested register...
         */
        throw new NotFound();
    }

    /**
     * debug endpoint, can be removed
     */
    public function getRegisterList()
    {
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));

        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        $this->response = $this->responseAsJson(
            [
                'registers' => $this->cacheHelper->getRegisters(),
            ]
        );
    }

    /**
     * debug endpoint, can be removed
     */
    public function getFieldList(string $registerCode)
    {
        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));

        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);

        $registers       = $this->cacheHelper->getRegisters();
        foreach ($registers as $register) {
            if (strtolower($register['code']) !== strtolower($registerCode)) {
                // the requested register Ignoring...
                continue;
            }

            $this->response = $this->responseAsJson(
                [
                    'register' => $register,
                    'fields' => $this->cacheHelper->getMetadataFields($register['id'])
                ]
            );
            return;
        }
        /**
         * Unable to find requested register...
         */
        throw new NotFound();
    }

    /**
     * Fetch data for user from ISAMS Register endpoints
     *
     * @param User $user // the user we want data for
     * @param array $register // array of the register object
     * @param array $registersConfig // approved list of fields to show
     * @return array
     */
    private function getDataFromRegister(User $user, array $register, array $registersConfig): array
    {
        $globalData  = $register;
        $userData = ['hasDateRange' => false];

        // Request 1.1.1: field data sources
        foreach ($this->cacheHelper->getMetadataFields($register['id']) as $metadataField) {
            // check if we are wanting this field...
            if (!in_array(strtolower($metadataField['key']), $registersConfig[strtolower($register['code'])])) {
                // not one of the requested field... ignoring
                continue;
            }
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
            $globalData['metadata'][strtolower($metadataField['key'])] = [
                'name'   => $metadataField['name'],
                'values' => $metadataFieldValues,
                'type'   => $metadataField['type']
            ];
        }

        // Collate student data
        foreach ($this->cacheHelper->getStudentFields($register['id'], $user->getExternalId()) as $key => $fieldValue) {
            $key = strtolower($key);
            // check if we are wanting this field...
            if (!in_array(strtolower($key), $registersConfig[strtolower($register['code'])])) {
                // not one of the requested field... ignoring
                continue;
            }
            if (isset($fieldValue)) {
                // switch for types of fields??? and TextboxField can also be links?
                $value = $globalData['metadata'][$key]['values'][$fieldValue] ?? $fieldValue;
                $type = $globalData['metadata'][$key]['type'];
                switch ($globalData['metadata'][$key]['type']) {
                    case 'TextboxField':
                        // am I a URL?
                        $url = new \Url($value);
                        if ($url->isUrl()) {
                            $type = 'url';
                        }
                        break;
                    case 'DateTimeField':
                        $value = new Alaress_Date_Format($value, Alaress_Date_Format::DATABASE_DATE_FORMAT);
                        break;
                    default:
                }
                $userData['fields'][$key] = [
                    'name'  => $globalData['metadata'][$key]['name'],
                    'value' => $value,
                    'type' => $type,
                ];
            }
        }
        foreach ($this->cacheHelper->getStudentDateRanges($register['id'], $user->getExternalId()) as $dateRange) {
            $userData['hasDateRange'] = true;
            $userData['dateRanges'][] = [
                'from' => new Alaress_Date_Format($dateRange['startDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
                'to'   => new Alaress_Date_Format($dateRange['endDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
            ];
        }
        return $userData;
    }
}
