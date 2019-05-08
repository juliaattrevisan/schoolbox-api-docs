<?php
/**
 * Profile
 *
 * Imports user_profile data from ISAMS xml feed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class Profile
{

    /**
     * @var Batchxml
     */
    private $api;


    /**
     * @var Usermapper
     */
    private $userMapper;


    /**
     * @var UserProfileMapper
     */
    private $userProfileMapper;


    /**
     * Profile constructor.
     *
     * @param UserMapper        $userMapper
     * @param UserProfileMapper $userProfileMapper
     */
    public function __construct(UserMapper $userMapper, UserProfileMapper $userProfileMapper)
    {
        $this->api = new Batchxml();
        $this->userMapper = $userMapper;
        $this->userProfileMapper = $userProfileMapper;
    }


    /**
     * Import into user_profile
     */
    public function import(): void
    {
        $students = $this->api->findAllStudents();

        foreach ($students as $student) {

            /* Firstly try to find out if the user already exists in schoolbox
             * If they don't exist, we can't assign a profile to them yet
             * We have to wait for the UPS to make this user
             */
            try {
                $user = $this->userMapper->findByExternalId($student->getId());
            } catch (Mapper\Exception $e) {
                continue;
            }

            /* Secondly try to find an existing profile for this user
             * If it already exists, update it; otherwise create it
             */
            try {
                $profile = $this->userProfileMapper->findById($user->getId());
            } catch (Mapper\Exception $e) {
                $profile = $this->userProfileMapper->createModel()->setUserId($user->getId());
            }

            /* Thirdly populate the profile instance with relevant data
             */

            /* Then save it
             */

            new \Alaress_Probe($profile);
        }
    }
}