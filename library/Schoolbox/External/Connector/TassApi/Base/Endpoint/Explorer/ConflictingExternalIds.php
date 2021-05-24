<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer;

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

/**
 * Class ConflictingExternalIds
 *
 * A test class for exploring TASS contents without having to do a sync
 * to see how I derived values
 *

<?php
require_once __DIR__ . '/../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$config = new \Schoolbox\Config\Loader();

$host = $config->get('schooldata_import_endpoint_host');
$dsn = $config->get('schooldata_import_endpoint_key');

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

$conflictingIds = new Endpoint\Explorer\ConflictingExternalIds([
Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
]);

$conflictingIds->explore();

die();

 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer
 */
class ConflictingExternalIds
{

    /**
     * @var array
     */
    private array $api;


    /**
     * CycleConfiguration constructor.
     *
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
    }


    /**
     * @throws \DebugBar\DebugBarException
     */
    public function explore()
    {
        $payload = [
            'students' => [],
            'teachers' => [],
            'employees' => []
        ];

        /* Process each current student, and their parents too
         */
        $students = $this->api[Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudents', [
            'currentstatus' => 'current'
        ]);

        foreach ($students['students'] as $student) {
            $externalId = trim((string)$student['general_details']['student_code']);
            $firstname = trim((string)$student['general_details']['first_name']);
            $lastname = trim((string)$student['general_details']['surname']);

            $payload['students'][$externalId] = "{$firstname} {$lastname}";
        }

        /* Get a list of teaching staff
         */
        $teachers = $this->api[Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeachers', [
            'currentstatus' => 'current'
        ]);

        foreach ($teachers['teachers'] as $teacher) {
            $externalId = trim((string)$teacher['teacher_code']);
            $firstname = trim((string)$teacher['given_names']);
            $lastname = trim((string)$teacher['surname']);
            $employeeCode = trim((string)$teacher['employee_code']);

            $payload['teachers'][$externalId] = "{$firstname} {$lastname}, {$employeeCode}";
        }

        /* Get a list of non-teaching staff
         */
        $employees = $this->api[Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getEmployees', [
            'user_code' => 'all'
        ]);

        foreach ($employees['employees'] as $employee) {
            $externalId = trim((string)$employee['user_code']);
            $firstname = trim((string)$employee['first_name']);
            $lastname = trim((string)$employee['surname']);
            $employeeCode = trim((string)$employee['user_code']);

            $payload['employees'][$externalId] = "{$firstname} {$lastname}, {$employeeCode}";
        }

        /* Identify conflicts
         */
        new \Alaress_Probe('Students x Teachers');
        new \Alaress_Probe(array_intersect_key($payload['students'], $payload['teachers']));
        new \Alaress_Probe('<hr />');

        new \Alaress_Probe('Teachers x Employees');
        new \Alaress_Probe(array_intersect_key($payload['teachers'], $payload['employees']));
        new \Alaress_Probe('<hr />');

        new \Alaress_Probe('Employees x Students');
        new \Alaress_Probe(array_intersect_key($payload['employees'], $payload['students']));
        new \Alaress_Probe('<hr />');

        /* Show each data set side by side
         */
        new \Alaress_Probe('Students', 'Teachers', 'Employees');
        new \Alaress_Probe($payload['students'], $payload['teachers'], $payload['employees']);
    }
}
