<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use Schoolbox\External\Connector\Kamar\Base\Endpoint\Specification as BaseSpecification;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain Profile-related nodes exist
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile
 */
class Specification extends BaseSpecification
{

    /**
     * Profile nodes
     *
     * @var array
     */
    protected $paths = [

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
    ];
}
