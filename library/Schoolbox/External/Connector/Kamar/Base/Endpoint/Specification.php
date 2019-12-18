<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Specification
{

    /**
     * Status text for existing nodes
     */
    private const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    private const STATUS_MISSING = 'MISSING';


    /**
     * Number of spaces for output formatting
     */
    private const PADDING = 20;


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * Specification constructor.
     *
     * @param LoggerInterface|null $log
     * @param Batchxml             $batchxml
     */
    public function __construct(?LoggerInterface $log, Batchxml $batchxml)
    {
        $this->log = $log;
        $this->batchxml = $batchxml;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */

            /* There is at least one current student
             */
            '/SMSDirectoryData/students/user',
            '/SMSDirectoryData/students/user/uniqueid',
            '/SMSDirectoryData/students/user/username',

            /* Profile info
             */
            '/SMSDirectoryData/students/user/house',
            '/SMSDirectoryData/students/user/datebirth',
            '/SMSDirectoryData/students/user/email',
            '/SMSDirectoryData/students/user/enrolmentdate',
            '/SMSDirectoryData/students/user/genderpreferred',
            '/SMSDirectoryData/students/user/house',
            '/SMSDirectoryData/students/user/leavingdate',
            '/SMSDirectoryData/students/user/mobile',
            '/SMSDirectoryData/students/user/nsn',
            '/SMSDirectoryData/students/user/tutor',
            '/SMSDirectoryData/students/user/yearlevel',
            '/SMSDirectoryData/students/user/boarder',
            '/SMSDirectoryData/students/user/esol',
            '/SMSDirectoryData/students/user/flags/conditions',
            '/SMSDirectoryData/students/user/flags/specialneeds',
            '/SMSDirectoryData/students/user/flags/notes',

            /**
             * Parent Profile nodes
             */

            '/SMSDirectoryData/students/user/caregivers/caregiver',
            '/SMSDirectoryData/students/user/caregivers/caregiver[@index]',
            '/SMSDirectoryData/students/user/caregivers/caregiver/name',
            '/SMSDirectoryData/students/user/caregivers/caregiver/email',
            '/SMSDirectoryData/students/user/caregivers/caregiver/mobile',
            '/SMSDirectoryData/students/user/caregivers/caregiver/relationship',

            /**
             * Staff Profile nodes
             */

            '/SMSDirectoryData/staff/user/uniqueid',
            '/SMSDirectoryData/staff/user/username',
            '/SMSDirectoryData/staff/user/title',
            '/SMSDirectoryData/staff/user/firstname',
            '/SMSDirectoryData/staff/user/lastname',
            '/SMSDirectoryData/staff/user/email',
            '/SMSDirectoryData/staff/user/mobile',
            '/SMSDirectoryData/staff/user/position',
            '/SMSDirectoryData/staff/user/groups',
            '/SMSDirectoryData/staff/user/groups/department',
            '/SMSDirectoryData/staff/user/groups/department/name',

            /**
             * Timetable nodes
             */
        ];

        $paddedLength = max(array_map('\strlen', $paths)) + self::PADDING;

        $messages = [];

        foreach ($paths as $path) {
            $paddedPath = str_pad($path, $paddedLength);

            if ($this->batchxml->fetchXml($path)) {
                $message = "{$paddedPath} " . self::STATUS_OK;

                if ($this->log) {
                    $this->log->info($message);
                }
            } else {
                $message = "{$paddedPath} " . self::STATUS_MISSING;

                if ($this->log) {
                    $this->log->warning($message);
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
