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
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as SchoolboxException;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Twig\Error\Error;

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
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache, IConfigLoader $configLoader)
    {
        // check if we have a valid register code
        // Hit the iSams API:
        $apiUrl = new Uri($configLoader->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
        // first, need to exchange client id/secret for an access token
        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $configLoader, $apiUrl);
    }

    /**
     * Get the register for a student.
     *
     * @param string $registerCode
     * @param User   $student
     * @return void
     * @throws ClientExceptionInterface
     * @throws Error
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws SchoolboxException
     * @throws \JsonException
     * @since                    23.1
     * @api                      GET /modules/isams/register/{register}/student/{studentId}
     */
    public function getStudent(string $registerCode, User $student): void
    {
        $adminConfig = $this->extractRegisterConfig($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_STUDENT_REGISTERS) ?? '');

        /**
         * fetch both the register id / details and the admin settings for that register
         * as specified by $registerCode
         */
        [$register, $config]       = $this->cacheHelper->getRegisters($registerCode, $adminConfig);
        if (null === $register) {
            /**
             * Unable to find requested register...
             */
            throw new NotFound();
        }

        $usersData = [
            [
                'user' => $student,
                'data' => $this->getUsersData($student, $register, $config),
            ]
        ];

        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            [
                'register' => $register,
                'studentData'   => $usersData,
            ],
        );
    }

    /**
     * Part 2: ISAMS: Financial Registers
     * Only parents can access this
     * it will show their children and the requested 'admin approved' financial register
     *
     * @param string $registerCode
     * @return void
     * @throws ClientExceptionInterface
     * @throws Error
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws SchoolboxException
     * @throws \JsonException
     * @api                      GET /modules/isams/register/{register}/parent
     * @since                    23.1
     */
    public function getParent(string $registerCode): void
    {
        $adminConfig = $this->extractRegisterConfig($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_FINANCIAL_REGISTERS) ?? '');

        /**
         * fetch both the register id / details and the admin settings for that register
         * as specified by $registerCode
         */
        [$register, $config]       = $this->cacheHelper->getRegisters($registerCode, $adminConfig);
        if (null === $register) {
            /**
             * Unable to find requested register...
             */
            throw new NotFound();
        }

        $externalUser = new \Schoolbox\External\User();
        $externalUser->loadChildren([$this->getUser()]);

        $usersData = [];
        foreach ($this->getUser()->getChildren() as $child) {
            $usersData[] = [
                'user' => $child,
                'data' => $this->getUsersData($child, $register, $config),
            ];
        }
        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            [
                'register' => $register,
                'studentData'   => $usersData,
            ],
        );
    }

//     Currently unused, may be needed when we start using a register with `RegisterWithRanges`...
//    /**
//     * Fetch data for user from ISAMS Register endpoints
//     *
//     * @param User $user // the user we want data for
//     * @param array $register // array of the register object
//     * @param array $registersConfig // approved list of fields to show
//     * @return array
//     */
//    private function getDataFromRegister(User $user, array $register, array $registersConfig): array
//    {
//        $globalData  = $register;
//        $userData = ['hasDateRange' => false];
//
//        // Request 1.1.1: field data sources
//        foreach ($this->cacheHelper->getMetadataFields($register['id']) as $metadataField) {
//            // check if we are wanting this field...
//            if (!in_array(strtolower($metadataField['key']), $registersConfig[strtolower($register['code'])])) {
//                // not one of the requested field... ignoring
//                continue;
//            }
//            // Fetch data source only if it's a list
//            $metadataFieldValues = null;
//            $dataSource          = strtolower($metadataField['properties']['dataSource'] ?? '');
//            if (in_array($dataSource, ['private', 'shared'])) {
//                if ($metadataField['properties']['dataSourceUrl'] ?? null) {
//                    foreach ($this->cacheHelper->getDataSource($metadataField['properties']['dataSourceUrl']) as $item) {
//                        $metadataFieldValues[$item['value']] = $item['text'];
//                    }
//                }
//            }
//            $globalData['metadata'][strtolower($metadataField['key'])] = [
//                'name'   => $metadataField['name'],
//                'values' => $metadataFieldValues,
//                'type'   => $metadataField['type']
//            ];
//        }
//
//        // Collate student data
//        foreach ($this->cacheHelper->getStudentFields($register['id'], $user->getExternalId()) as $key => $fieldValue) {
//            $key = strtolower($key);
//            // check if we are wanting this field...
//            if (!in_array(strtolower($key), $registersConfig[strtolower($register['code'])])) {
//                // not one of the requested field... ignoring
//                continue;
//            }
//            if (isset($fieldValue)) {
//                // switch for types of fields??? and TextboxField can also be links?
//                $value = $globalData['metadata'][$key]['values'][$fieldValue] ?? $fieldValue;
//                $type = $globalData['metadata'][$key]['type'];
//                switch ($globalData['metadata'][$key]['type']) {
//                    case 'TextboxField':
//                        // am I a URL?
//                        $url = new \Url($value);
//                        if ($url->isUrl()) {
//                            $type = 'url';
//                        }
//                        break;
//                    case 'DateTimeField':
//                        $value = new Alaress_Date_Format($value, Alaress_Date_Format::DATABASE_DATE_FORMAT);
//                        break;
//                    default:
//                }
//                $userData['fields'][$key] = [
//                    'name'  => $globalData['metadata'][$key]['name'],
//                    'value' => $value,
//                    'type' => $type,
//                ];
//            }
//        }
//        foreach ($this->cacheHelper->getStudentDateRanges($register['id'], $user->getExternalId()) as $dateRange) {
//            $userData['hasDateRange'] = true;
//            $userData['dateRanges'][] = [
//                'from' => new Alaress_Date_Format($dateRange['startDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
//                'to'   => new Alaress_Date_Format($dateRange['endDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT),
//            ];
//        }
//        return $userData;
//    }

    /**
     * Extract common config settings
     *
     *  Assumed structure of multi dimensional csv:
     *  register1;recordCollection3;field1,field2,field6
     *  register2;recordCollection99;field1
     *  register6;recordCollection2;field7,field12
     *
     * @param string $registerConfig a multi dimensional csv
     * @return array
     * @throws SchoolboxException
     */
    private function extractRegisterConfig(string $registerConfig): array
    {
        $registersConfig = [];
        /**
         * Assumed structure of multi dimensional csv:
         *   register1;recordCollection3;field1,field2,field6
         *   register2;recordCollection99;field1
         *   register6;recordCollection2;field7,field12
         */
        // explode to find how many registers we will be working with...
        foreach (preg_split('/\R/', $registerConfig) as $config) {
            // explode again to extract the registers, and their fields
            // first object entry is the register, later items are the allowed fields
            /**
             * this explode on `,` may really break... we haven't seen any codes with comma's in them just yet...
             * but that doesn't mean they don't exist
             * we may need to go to a dns and suds like config page
             */
            $tmp = explode(';', $config);
            //if we dont have 3 and exactly 3 items in this config, exit and error.
            if (3 !== count($tmp)) {
                throw new SchoolboxException("iSAMAS Register Config Invalid");
            }
            $registerCode = $tmp[0];
            $registerCollection = $tmp[1];
            //Group all the filters with the same group code
            $registersConfig[strtolower($registerCode)][] = [
                'register'          => $registerCode,
                'recordCollection'  => $registerCollection,
                'fields'            => explode(',', $tmp[2])
            ];
        }
        return $registersConfig;
    }

    /**
     * common function to fetch / build register info for an array of users
     *
     * @param User  $user     who are we building info for
     * @param array $register what is the requested register we want to look into
     * @param array $config   the admin approved register list
     * @return array
     * @throws InvalidArgumentException
     */
    private function getUsersData(User $user, array $register, array $config): array
    {
        $metaFields = $this->extractMetaFields($register['id']);

        $out = [];

        $factory = new GroupTypeFactory($this->cacheHelper, $user, $register, $metaFields);
        foreach ($config as $conf) {
            $collectionName = $conf['recordCollection'];

            //Skip if the fields are not in the metadata fields returned
            if (!isset($metaFields[$collectionName])) {
                continue;
            }

            $metaGroupType = $metaFields[$collectionName]['type'];
            if (!isset($out[$metaGroupType][$collectionName]['groupName'])) {
                $out[$metaGroupType][$collectionName]['groupName'] = $metaFields[$collectionName]['name'];
            }
            $groupType = $factory->produce(
                $metaGroupType,
                $conf
            );

            //If the type does not exist. Skip.
            if (null === $groupType) {
                continue;
            }

            $out = $groupType->process($out);
        }
        return $out;
    }

    /**
     * Extract and formatting meta fields with their relevant groups
     *
     * @param   int $registerId
     * @return  array
     * @throws  InvalidArgumentException
     */
    private function extractMetaFields(int $registerId): array
    {
        $metadataFields = $this->cacheHelper->getMetadataFields($registerId);
        $metaFields = [];

        foreach ($metadataFields as $field) {
            $groupData = $field['group'];
            $groupKey = $groupData['key'];
            if (!isset($metaFields[$groupKey])) {
                $metaFields[$groupKey] = [
                    'type' => $groupData['type'],
                    'id' => $groupData['id'],
                    'name' => $groupData['name'],
                ];
            }

            /**
             * NOTE:
             * register metadata fields keys all words are caps,
             * the student data fields first character is lowercase...
             *
             * Regarding the capitalisation, I know this happens elsewhere with OData filters
             * – they always flip the capitals…
             * -Mark@EIM
             *
             * so we need to keep the config as 1 of the schemes,
             * I decided that the case should match the student record, not the metadata endpoints
             * -Justin
             *
             */

            $metaFields[$groupKey]['fields'][lcfirst($field['key'])] = [
                'name' => $field['name'],
                'type' => $field['type']
            ];
        }
        return $metaFields;
    }

    /**
     * *****************************************************************************************************************
     * debug endpoint
     */

//    /**
//     * get a list of all registers whilst ignoring the admin filters
//     *
//     * @return void
//     * @throws ClientExceptionInterface
//     * @throws GuzzleException
//     * @throws InvalidArgumentException
//     * @throws \JsonException
//     * @api   GET /modules/isams/register/list
//     * @since 23.1
//     */
//    public function getRegisterList()
//    {
//        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
//
//        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);
//
//        $this->response = $this->responseAsJson(
//            [
//                'registers' => $this->cacheHelper->getRegisters(),
//            ]
//        );
//    }
//
//    /**
//     * get a list of all fields for a specified register whilst ignoring the admin filters
//     *
//     * @param string $registerCode
//     * @return void
//     * @throws ClientExceptionInterface
//     * @throws GuzzleException
//     * @throws InvalidArgumentException
//     * @throws \JsonException
//     * @api   GET /modules/isams/register/{registerCode}/getFieldList
//     * @since 23.1
//     */
//    public function getFieldList(string $registerCode)
//    {
//        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
//
//        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);
//
//        $registers       = $this->cacheHelper->getRegisters();
//        foreach ($registers as $register) {
//            if (strtolower($register['code']) !== strtolower($registerCode)) {
//                // the requested register Ignoring...
//                continue;
//            }
//
//            $this->response = $this->responseAsJson(
//                [
//                    'register' => $register,
//                    'fields' => $this->cacheHelper->getMetadataFields($register['id'])
//                ]
//            );
//            return;
//        }
//        /**
//         * Unable to find requested register...
//         */
//        throw new NotFound();
//    }
//
//    /**
//     * get all student fields for a specified student
//     *
//     * @param User $student
//     * @return void
//     * @throws ClientExceptionInterface
//     * @throws GuzzleException
//     * @throws InvalidArgumentException
//     * @throws \JsonException
//     * @api   GET /modules/isams/register/student/{studentId}
//     * @since 23.1
//     */
//    public function getAllStudent(User $student)
//    {
//        $apiUrl = new Uri($this->getConfigLoader()->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_HOST));
//
//        $this->cacheHelper = new CacheHelper($this->httpClient, $this->cache, $this->getConfigLoader(), $apiUrl);
//        $data = [];
//        foreach ($this->cacheHelper->getRegisters() as $register) {
//            $data[] = [
//                    'register' => $register,
//                    'data'  => $this->cacheHelper->getStudentFields($register['id'], $student->getExternalId())
//                    ];
//        }
//        $this->response = $this->responseAsJson(
//            $data
//        );
//    }
}
