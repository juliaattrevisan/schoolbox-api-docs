<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api;

use External\Tass\Api\Exception\InvalidArg;
use External\Tass\Api\Exception\NotFound;
use External\Tass\Api\Exception\ResponseDecodeException;
use External\Tass\Api\Exception\UdParseException;
use External\Tass\Api\Exception\Unauthorised;
use GuzzleHttp;
use GuzzleHttp\ClientInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Token as TokenCodec;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\GenericCollection;
use RuntimeException;

use function in_array;

/**
 * Responsible for connecting to the Tass2 HTTP API and returning strongly-typed models for consumption by Schoolbox code.
 *
 * This implementation should know nothing of the data-structures returned by Tass - that is deferred to the model factory.
 */
class Connector
{

    // Known Tass2 HTTP API methods
    private const METHOD_GET_STUDENT = 'GetStudent';
    private const METHOD_GET_STUDENT_UD = 'GetStudentUD';
    private const METHOD_GET_STUDENT_ADDRESSES = 'GetStudentAddresses';
    private const METHOD_GET_PARENT = 'GetParent';
    private const METHOD_GET_STUDENT_SUBJECT_LIST = 'GetStudentSubjectList';
    private const METHOD_GET_TEACHER_SUBJECT_LIST = 'GetTeacherSubjectList';
    private const METHOD_GET_TIMETABLE_SETUP = 'GetTimetableSetup';
    private const METHOD_GET_ENROLLED_STUDENT_LIST = 'GetEnrolledStudentList';
    private const METHOD_GET_CLASS_STUDENT_LIST = 'GetClassStudentList';
    private const METHOD_GET_CLASS_TEACHER_LIST = 'GetClassTeacherList';
    private const METHOD_GET_TIMETABLE_DEFINITIONS = 'GetTimetableDefinitions';
    private const METHOD_GET_STUDENT_TIMETABLE = 'GetStudentTimetable';
    private const METHOD_GET_TEACHER_TIMETABLE = 'GetTeacherTimetable';
    private const METHOD_GET_DAY_NUMBER = 'GetDayNumber';

    // Magic value that indicates to get all timetables, not just a single timetable
    private const TIMETABLE_ID_ALL = 'all';

    // Tass2 API expected date format, eg '11/05/2017'
    private const TASS_DATE_FORMAT = 'd/m/Y';

    /** @var ModelFactory */
    private $modelFactory;

    /** @var ClientInterface|GuzzleHttp\Client */
    private $httpClient;

    /** @var string */
    private $apiUrl;

    /** @var TokenCodec */
    private $tokenBuilder;

    /** @var string */
    private $appCode;

    /** @var string */
    private $companyCode;


    /**
     * @param ModelFactory    $responseFactory
     * @param ClientInterface $client
     * @param TokenCodec      $tokenBuilder
     * @param string          $apiUrl
     * @param string          $companyCode
     * @param string          $appCode
     */
    public function __construct(
        ModelFactory $responseFactory,
        ClientInterface $client,
        TokenCodec $tokenBuilder,
        string $apiUrl,
        string $companyCode,
        string $appCode
    ) {
        $errors = $this->validateConstructionArgs($apiUrl, $companyCode, $appCode);
        if (\count($errors)) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        $this->modelFactory = $responseFactory;
        $this->httpClient   = $client;
        $this->tokenBuilder = $tokenBuilder;
        $this->apiUrl       = $apiUrl;
        $this->companyCode  = $companyCode;
        $this->appCode      = $appCode;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Tass API methods
    // ---------------------------------------------------------------------------------------------------------------


    /**
     * TODO return object
     *
     * @param string $currentStatus
     * @param bool   $includePhone
     * @param bool   $thumbnail
     * @return array
     * @throws InvalidArg
     */
    public function getTeachers(string $currentStatus = 'current', bool $includePhone = false, bool $thumbnail = false): array
    {
        // TODO defined the magic strings as constants
        if (!in_array($currentStatus, ['current', 'noncurrent'], true)) {
            throw new InvalidArg("Invalid currentStatus arg: '$currentStatus'");
        }

        $methodArgs = [
            'currentstatus' => $currentStatus,
            'includephoto'  => $includePhone,
            'thumbnail'     => $thumbnail,
        ];

        return $this->executeMethod('getTeachers', $methodArgs);
    }


    /**
     * Returns a structure of Student data
     *
     * @param string $studentCode
     * @return Models\Student
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getStudent(string $studentCode): Models\Student
    {
        $methodArgs = [
            'studcode' => $studentCode,
        ];
        return $this->modelFactory->buildStudent($this->executeMethod(self::METHOD_GET_STUDENT, $methodArgs));
    }


    /**
     * Returns a standard UD data for a student, or Extended UD data for the UD area specified.
     *
     * @todo UD = 'User-Defined'? Maybe? If so, name all such usages explicitly
     *
     * @param string   $studentCode
     * @param int|null $areaCode
     * @return Models\StudentUD
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getStudentUD(string $studentCode, int $areaCode = null): Models\StudentUD
    {
        $methodArgs = [
            'studcode' => $studentCode,
        ];
        if (null !== $areaCode) {
            $methodArgs['areacode'] = $areaCode;
        }
        return $this->modelFactory->buildStudentUd($this->executeMethod(self::METHOD_GET_STUDENT_UD, $methodArgs));
    }


    /**
     * Returns all student addresses based on Communication Rules, or for a specific communication type if specified.
     *
     * @param string      $studentCode
     * @param null|string $commType
     * @return Models\StudentAddresses
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getStudentAddresses(string $studentCode, string $commType = null): Models\StudentAddresses
    {
        $methodArgs = [
            'studcode' => $studentCode,
        ];
        if (null !== $commType) {
            $methodArgs['commtype'] = $commType;
        }
        return $this->modelFactory->buildStudentAddresses($this->executeMethod(self::METHOD_GET_STUDENT_ADDRESSES, $methodArgs));
    }


    /**
     * Returns Parent record details for the specified parent code.
     *
     * @param string $parentCode
     * @return Models\ParentDetails
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getParent(string $parentCode): Models\ParentDetails
    {
        $methodArgs = [
            'parcode' => $parentCode,
        ];
        return $this->modelFactory->buildParent($this->executeMethod(self::METHOD_GET_PARENT, $methodArgs));
    }


    /**
     * Returns current subjects for a specific Student.
     *
     * @param string $studentCode
     * @return Models\SubjectList
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getStudentSubjectList(string $studentCode): Models\SubjectList
    {
        $methodArgs = [
            'studcode' => $studentCode,
        ];
        return $this->modelFactory->buildSubjectList($this->executeMethod(self::METHOD_GET_STUDENT_SUBJECT_LIST, $methodArgs));
    }


    /**
     * Returns current subjects for a specific Teacher..
     *
     * @param string $teacherCode
     * @return Models\SubjectList
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getTeacherSubjectList(string $teacherCode): Models\SubjectList
    {
        $methodArgs = [
            'tchcode' => $teacherCode,
        ];
        return $this->modelFactory->buildSubjectList($this->executeMethod(self::METHOD_GET_TEACHER_SUBJECT_LIST, $methodArgs));
    }


    /**
     * Returns the timetable parameter setup information with or without contact information.
     *
     * @param bool $contacts
     * @return Models\TimetableSetup
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getTimetableSetup(bool $contacts): Models\TimetableSetup
    {
        $methodArgs = [
            'contacts' => $contacts ? 'true' : 'false',
        ];
        return $this->modelFactory->buildTimetableSetup($this->executeMethod(self::METHOD_GET_TIMETABLE_SETUP, $methodArgs), $contacts);
    }


    /**
     * Returns all current student enrolment records for a specified Student
     *
     * Note that both this and ::getParentEnrolledStudentList() share the same API method
     *
     * @param string $studentCode
     * @return Models\EnrolledStudent
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getStudentEnrolledStudentList(string $studentCode): Models\EnrolledStudent
    {
        $params = [
            'studcode' => $studentCode,
        ];
        return $this->modelFactory->buildEnrolledStudent($this->executeMethod(self::METHOD_GET_ENROLLED_STUDENT_LIST, $params));
    }


    /**
     * Returns all current student enrolment records for a specified Parent.
     *
     * Note that both this and ::getParentEnrolledStudentList() share the same API method
     *
     * @param string $parentCode
     * @return Models\EnrolledStudent[]|GenericCollection
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getParentEnrolledStudentList(string $parentCode): GenericCollection
    {
        $params = [
            'parcode' => $parentCode,
        ];
        return $this->modelFactory->buildEnrolledStudentList($this->executeMethod(self::METHOD_GET_ENROLLED_STUDENT_LIST, $params));
    }


    /**
     * Returns all students for a specified subject.
     *
     * @param string      $subjectCode
     * @param string|null $yearGroup Required if non unitised. Use getTimetableSetup()->isUnitised()
     * @return Models\EnrolledStudent[]|GenericCollection
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getClassStudentList(string $subjectCode, string $yearGroup = null): GenericCollection
    {
        $params = [
            'subcode' => $subjectCode,
        ];
        if (null !== $yearGroup) {
            $params['yeargrp'] = $yearGroup;
        }
        return $this->modelFactory->buildClassStudentList($this->executeMethod(self::METHOD_GET_CLASS_STUDENT_LIST, $params));
    }


    /**
     * Returns all teachers for a specified subject.
     *
     * @param string      $subjectCode
     * @param string      $classCode
     * @param string|null $yearGroup Required if non unitised. Use getTimetableSetup()->isUnitised()
     * @return Models\Teacher[]|GenericCollection
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws UdParseException If cannot parse UD data
     */
    public function getClassTeacherList(string $subjectCode, string $classCode, string $yearGroup = null): GenericCollection
    {
        $params = [
            'subcode' => $subjectCode,
            'class'   => $classCode,
        ];
        if (null !== $yearGroup) {
            $params['yeargrp'] = $yearGroup;
        }
        return $this->modelFactory->buildClassTeacherList($this->executeMethod(self::METHOD_GET_CLASS_TEACHER_LIST, $params));
    }


    /**
     * Returns full timetable for all timetables, or for a specified timetable.
     *
     * @return Models\TimetableDefinitions
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @todo should this return a collection of timetables?
     */
    public function getAllTimetableDefinitions(): Models\TimetableDefinitions
    {
        $params = [
            'id' => self::TIMETABLE_ID_ALL,
        ];

        return $this->modelFactory->buildTimetableDefinitions($this->executeMethod(self::METHOD_GET_TIMETABLE_DEFINITIONS, $params));
    }


    /**
     * Returns full timetable for all timetables, or for a specified timetable.
     *
     * @param int $timeTableId Fetches all timetables if no id specified
     * @return Models\TimetableDefinitions
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     * @throws NotFound If no timetable found for the specified ID
     */
    public function getTimetableDefinition(int $timeTableId): Models\TimetableDefinitions
    {
        $params = [
            'id' => $timeTableId,
        ];

        return $this->modelFactory->buildTimetableDefinition($timeTableId, $this->executeMethod(self::METHOD_GET_TIMETABLE_DEFINITIONS, $params));
    }


    /**
     * Returns the full timetable for the specified Student, or the daily timetable for the student on the specified date.
     *
     * @param string                   $studentCode
     * @param Alaress_Date_Format|null $date
     * @return Models\StudentTimetable[]
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getStudentTimetable(string $studentCode, Alaress_Date_Format $date = null): array
    {
        $params = [
            'studcode' => $studentCode,
        ];
        if (null !== $date && !$date instanceof Alaress_Date_NullDate) {
            $params['date'] = $date->getUsingFormat(self::TASS_DATE_FORMAT);
        }
        return $this->modelFactory->buildStudentTimetable($this->executeMethod(self::METHOD_GET_STUDENT_TIMETABLE, $params));
    }


    /**
     * Returns the full timetable for the specified Teacher, or the daily timetable for the teacher on the specified date.
     *
     * @param string                   $teacherCode
     * @param Alaress_Date_Format|null $date
     * @return Models\TeacherTimetable[][]
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getTeacherTimetable(string $teacherCode, Alaress_Date_Format $date = null): array
    {
        $params = [
            'tchcode' => $teacherCode,
        ];
        if (null !== $date && !$date instanceof Alaress_Date_NullDate) {
            $params['date'] = $date->getUsingFormat(self::TASS_DATE_FORMAT);
        }
        return $this->modelFactory->buildTeacherTimetable($this->executeMethod(self::METHOD_GET_TEACHER_TIMETABLE, $params));
    }


    /**
     * Returns the current day number for the specified date for all timetables, or for the specified timetable only.
     *
     * @param Alaress_Date_Format|null $date
     * @param int|null                 $timeTableId
     * @return Models\DayNumber
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    public function getDayNumber(Alaress_Date_Format $date, int $timeTableId = null): Models\DayNumber
    {
        $params = [
            'date' => $date->getUsingFormat(self::TASS_DATE_FORMAT),
        ];
        if (null !== $timeTableId) {
            $params['id'] = $timeTableId;
        }
        return $this->modelFactory->buildDayNumber($this->executeMethod(self::METHOD_GET_DAY_NUMBER, $params));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Returns an array of reasons why the specified construction args are invalid
     *
     * @param string $apiUrl
     * @param string $companyCode
     * @param string $appCode
     * @return string[]
     */
    private function validateConstructionArgs(string $apiUrl, string $companyCode, string $appCode): array
    {
        $errors = [];
        if ('' === trim($apiUrl)) {
            $errors[] = 'Api URL cannot be empty';
        }
        if ('' === trim($companyCode)) {
            $errors[] = 'Company Code cannot be empty';
        }
        if ('' === trim($appCode)) {
            $errors[] = 'App Code cannot be empty';
        }
        return $errors;
    }


    /**
     * Returns a alphabetically-sorted array of data from Tass API or throws an exception why
     *
     * @param string $method Which API method to call
     * @param array  $params Passed in with API call
     * @return array
     * @throws Unauthorised If credentials are incorrect
     * @throws InvalidArg If method args are invalid
     * @throws ResponseDecodeException If unable to decode API response
     */
    private function executeMethod(string $method, array $params): array
    {
        $queryArgs = [
            'method'  => $method,
            'appcode' => $this->appCode,
            'company' => $this->companyCode,
             'v'      => 2,
            'token'   => $this->tokenBuilder->generateToken($params, true),
        ];

        try {
            $response = $this->httpClient->get($this->apiUrl, ['query' => $queryArgs]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            // TODO check that this is only thrown on invalid credentials, as opposed to invalid method args
            // TODO this is being thrown on test API for getStudent('BELL005') with msg '401 Enable Photos is set to No' - not sure what that means?
            // TODO if that's being caused by invalid method args, it should be cast to InvalidArg, not Unauthorised
            $response = $e->getResponse();
            throw new Unauthorised($response->getReasonPhrase(), $response->getStatusCode(), $e);
        }

        // TODO check returned token timestamp for MITM replay

        if (!$this->isSuccessful($response)) {
            // TODO may not be able to determine this yet without decoding, eg logical fail may still be 200, but contains an invalid flag
            // TODO. if so, then this can be deleted.
            throw new InvalidArg();
        }

        $decoded = $this->decodeResponse($response);

        // TODO check flags in response to ensure the API call succeeded
        $this->assertValidResponse($decoded);

        return $this->kSortRecursive($decoded);
    }


    /**
     * Returns the response's body contents as an associative array, or throws exception
     *
     * @param ResponseInterface $response
     * @return array
     * @throws ResponseDecodeException If unable to decode the response's body into
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        try {
            $decoded = json_decode($response->getBody()->getContents(), true);
        } catch (RuntimeException $e) {
            throw new ResponseDecodeException('Unable to decode API response', $e->getCode(), $e);
        }

        // Protect against type-errors
        if (!\is_array($decoded)) {
            throw new ResponseDecodeException("Unable to decode API response into expected array: {$decoded}");
        }

        return $decoded;
    }


    /**
     * TODO check against real data flags
     *
     * @param ResponseInterface $response
     * @return bool
     */
    private function isSuccessful(ResponseInterface $response): bool
    {
        return true;
        // TODO check for error messages, expected flags etc
//        $statusCode = $response->getStatusCode();
//        if ($statusCode < 200) {
//            return false;
//        }
//        if ($statusCode >= 300) {
//            return false;
//        }
//        return true;
    }


    /**
     * Throws exception if the decoded response contains flags that indicates the API call failed
     *
     * @param array $decodedResponse
     * @throws InvalidArg
     */
    private function assertValidResponse(array $decodedResponse): void
    {
        // TODO inspect flags __invalid etc to determine if the method call failed for some reason
        // TODO check we can call this across all methods, rather than implemented per method
        if (false) {
            throw new InvalidArg();
        }
    }


    /**
     * Returns a recursively key-sorted array.
     *
     * This is mainly for developer-convenience. Some data structures returned from the API are very long,
     * and it's much easier to find specific attributes if they're sorted.
     *
     * @param array $array
     * @param int   $sortFlags
     * @return array
     */
    private function kSortRecursive(array $array, $sortFlags = SORT_REGULAR): array
    {
        \ksort($array, $sortFlags);

        foreach ($array as $idx => $subArray) {
            if (\is_array($subArray)) {
                $array[$idx] = $this->kSortRecursive($subArray, $sortFlags);
            }
        }
        return $array;
    }
}
