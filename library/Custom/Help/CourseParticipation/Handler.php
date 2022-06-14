<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Custom\Help\CourseParticipation\Exception\ParticipantRolesNotFoundException;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\RoleMapper;
use Schoolbox\Admin\SHAPS\Auth\Digest;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Handler
{
    public const PARTICIPANT_ROLE_NAMES = ['School Staff', 'Hero Teacher', 'Prospect'];
    public const DEFAULT_PARTICIPANT_ROLE_NAME = 'School Staff';

    public const PARTICIPANT_CAMPUS = 1;
    public const REQUIRED_USER_FIELDS = ['id', 'email', 'firstname', 'lastname'];

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var SiteAccess\Mapper
     */
    private $tokenMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Folder\Code\Mapper
     */
    private $codeMapper;

    /**
     * @var User\Password\MailService
     */
    private $passwordMailService;


    /**
     * @param UserAccessMapper               $userAccessMapper
     * @param SiteAccess\Mapper              $tokenMapper
     * @param Folder\Mapper                  $folderMapper
     * @param User\Password\MailService      $mailService
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper,
        User\Password\MailService $mailService
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper = $tokenMapper;
        $this->folderMapper = $folderMapper;
        $this->codeMapper = $folderMapper->getJoinMapper('codes');
        $this->passwordMailService = $mailService;
    }


    /**
     * @param string $schoolCode
     * @return array
     * @throws Mapper\Exception      If schoolCode does not have a course area on help
     */
    public function getFolderAccessBySchool(string $schoolCode): array
    {
        // ok, if you're here, you can see the thing.
        $courses = $this->getCoursesBySchool($schoolCode);

        $payload = [];

        foreach ($courses as $course) {
            $participants = $this->userAccessMapper->findUsersInCategory($course);
            $users = [];

            foreach ($participants as $user) {
                $users[] = [
                    "id" => $user->getExternalId(),
                    "firstname" => $user->getFirstname(),
                    "lastname" => $user->getLastname()
                ];
            }

            foreach ($users as $i => $user) {
                // Existing live data on Help appropriates the Year field to identify which school users are from.
                // Going forward we should be using the external ID prefixed with school code instead, but we have to work with what we've got.
                // The 'fuzzy' case was meant to look for the school code in the Year field instead, and if that
                // was missing or didn't match our given school code, we send the client a null ID so the client
                // doesn't try to find a user that can't be matched on their instance.

                // However, due to issues with the cron script that generates the external IDs for users,
                // none of these IDs will ever match with users on the instance, so I am commenting this out for now
                // until we sort out some kind of migration.

                //if ($fuzzy) {
                    // if we're doing a fuzzy lookup, search by `year`
                    //$map = json_decode(file_get_contents(__DIR__ . '/code-map.json'), true);

                    //if (empty($map[$schoolCode])) {
                        // we don't have this code mapped, therefore nobody matches
                    //}
                //}

                // Look up users by external ID
                $matches = "";
                $currentUserId = $users[$i]["id"];
                preg_match("/" . preg_quote($schoolCode) . "\.(\d*)/", $currentUserId, $matches);
                if (! empty($matches)) {
                    $users[$i]["id"] = $matches[1];
                } else {
                    // This user didn't have a school code, so give them a null ID
                    $users[$i]["id"] = null;
                }
            }

            $payload[] = [
                'folderName'   => $course->getName(),
                'folderId'     => $course->getId(),
                'participants' => $users
            ];
        }

        return $payload;
    }


    /**
     * @param string $schoolCode
     * @param bool   $fuzzy
     * @return array
     * @throws Mapper\DefinitionException
     * @throws ParticipantRolesNotFoundException If the expected roles are not found on this server
     */
    public function getUsersBySchool(string $schoolCode, bool $fuzzy = false): array
    {
        $roleIds = $this->getRoleMapper()->findIdsByColumn("name", self::PARTICIPANT_ROLE_NAMES);

        if (!\count($roleIds)) {
            // No roles with these names on this server? This most likely means the server is misconfigured.
            // Note this is not a case where we should simply return an empty set of users.
            // An empty set implies the role exists, but no users have that role for the specified school code.
            $roleNames = \implode(',', self::PARTICIPANT_ROLE_NAMES);
            throw new ParticipantRolesNotFoundException("No roles found for roles names: [{$roleNames}]");
        }

        // show the list of users for this school
        // filter to the list of users whose external IDs are prefixed with the given school code
        $predicate = $this->getUserMapper()->getPredicateBuilder()->in('group_id', $roleIds);

        // TODO remove the fuzzy search case, because it can't match Help users to their local Schoolbox instance

        // Existing live data on Help appropriates the Year field to identify which school users are from.
        // Going forward we should be using the external ID prefixed with school code instead, but we have to work with what we've got.
        // The 'fuzzy' case was meant to look for the school code in the Year field instead, and if that
        // was missing or didn't match our given school code, we send the client a null ID so the client
        // doesn't try to find a user that can't be matched on their instance.

        // However, due to issues with the cron script that generates the external IDs for users,
        // none of these IDs will ever match with users on the instance, so it should be removed once a migration
        // path has been determined.

        if ($fuzzy) {
            // if we're doing a fuzzy lookup, search by `year`
            /** @var string[][] $map */
            $map = json_decode(file_get_contents(__DIR__ . '/code-map.json'), true);

            if (empty($map[$schoolCode])) {
                // we don't have this code mapped, therefore nobody matches
                return [];
            }

            $predicate ->in('year', $map[$schoolCode]);
        } else {
            // "normal" behaviour is to look up by external IDs
            $predicate->like('synergy_id', "{$schoolCode}.%");
        }

        // we only need the external ID itself, so bypass object hydration
        $rows = $this->getUserMapper()->findColumnsByPredicates([ 'username', 'synergy_id' ], $predicate);

        $unprefixed = array_map(function (array $row) use ($schoolCode) {
            $row['synergy_id'] = str_replace("{$schoolCode}.", '', $row['synergy_id']);
            return $row;
        }, $rows);

        return array_combine(
            array_column($unprefixed, 'synergy_id'),
            array_column($unprefixed, 'username')
        );
    }


    /**
     * @param string   $schoolCode
     * @param bool     $byUsername
     * @param iterable $post
     * @return array
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function postUserRegistrations(string $schoolCode, bool $byUsername, iterable $post): array
    {
        $participantRole     = $this->getRoleMapper()->findOneByColumn('name', self::DEFAULT_PARTICIPANT_ROLE_NAME);
        $participantCampuses = $this->getCampusMapper()->findByColumn('id', self::PARTICIPANT_CAMPUS);

        $failedUsers = [];

        foreach ($post['user'] as $userData) {
            if (!$this->userPayloadIsValid($userData)) {
                $failedUsers[] = [
                    'Failed: required data was missing', $userData
                ];
                continue;
            }

            $externalId = "{$schoolCode}.{$userData['id']}";

            try {
                if ($byUsername) {
                    // if explicitly stated by the request, look up user by username
                    $user = $this->getUserMapper()->findOneByColumn('username', $userData['email']);
                } else {
                    // consider user uniqueness as looked up by external ID
                    $user = $this->getUserMapper()->findOneByColumn('synergy_id', $externalId);
                }

                // If the user exists, but not in the role, fail.
                if ($participantRole->getId() !== $user->getRole()->getId()) {
                    $failedUsers[] = [
                        "Failed: user already exists. Please contact support.",
                        $userData,
                    ];
                    continue;
                }

                // cache the old values
                $oldUsername   = $user->getUsername();
            } catch (Mapper\Exception $e) {
                // user does not exist or deleted; create the model
                $user = $this->getUserMapper()->createModel()
                    ->setRole($participantRole)
                    ->setCampusCollection($participantCampuses)
                    ->setLocalEmail(false)
                    ->setWebmail(false)
                    ->setUseInternalMail(false)
                    ->setEportfolioEnable(true)
                ;

                // no previous data
                $oldUsername   = '';
            }

            // update user object
            $user
                ->setUsername($userData['email']) // yes, email -> username is on purpose
                ->setTitle($userData['title'] ?? '')
                ->setFirstname($userData['firstname'])
                ->setLastname($userData['lastname'])
                ->setAltEmail($userData['email'])
                ->setEnabled(true)
                ->setExternalId($externalId)
                ->setLocalEmail(false) // use alt-email in ALL cases always
            ;

            try {
                $this->getUserMapper()->save($user);

                if ($oldUsername) {
                    // existing user
                    if ($user->getUsername() !== $oldUsername) {
                        // send existing-user email ("your username has changed")
                        $this->passwordMailService->sendUsernameChangeEmailToUser($user, $oldUsername);
                    }
                } else {
                    // new user: send new-user email ("welcome and here's how to set your password")
                    $this->passwordMailService->sendWelcomeEmailToUser($user);
                }
            } catch (Mapper\Exception $e) {
                // the user failed to save; is fail case
                $failedUsers[] = [
                    'Failed to save', $userData
                ];
            } catch (UnexpectedValueException $e) {
                // duplicate username
                $failedUsers[] = [
                    'Duplicate username', $userData
                ];
            }
        }

        return $failedUsers;
    }

    /**
     * Compute a digest auth for the given school code and timestamp.
     *
     * Mash ingredients thoroughly in a small bowl, seasoning with a little salt to taste.
     *
     * @param int                 $timestamp
     * @param string              $schoolCode
     * @param string              $accessToken
     * @return string
     */
    public function computeAuthDigest(int $timestamp, string $schoolCode, string $accessToken): string
    {
        return (new Digest($schoolCode, $accessToken))->generate($timestamp);
    }


    /**
     * @param string $schoolCode
     * @return Collection|IFolder[]
     * @throws Mapper\Exception      If there are no courses for the specified schoolCode
     */
    private function getCoursesBySchool(string $schoolCode): Collection
    {
        // ok, if you're here, you can see the thing.
        $code   = $this->codeMapper->findOneByColumn('code', $schoolCode);
        $folder = $this->folderMapper->findById($code->getFolder()->getId());

        // obtain all direct descendants of this folder
        // the scope of implementation is immediate children only, rather than attempting to gather all descendants
        return $this->folderMapper->findByParentId($folder->getId());
    }


    /**
     * @param array $userData
     * @return bool
     */
    private function userPayloadIsValid(array $userData): bool
    {
        // check all the necessary data is present
        foreach (self::REQUIRED_USER_FIELDS as $required) {
            if (!array_key_exists($required, $userData) || empty(trim($userData[$required]))) {
                return false;
            }
        }

        return true;
    }


    /**
     * @return UserMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getUserMapper(): UserMapper
    {
        return $this->userAccessMapper->getJoinMapper('user');
    }


    /**
     * @return RoleMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getRoleMapper(): RoleMapper
    {
        return $this->getUserMapper()->getJoinMapper('role');
    }


    /**
     * @return CampusMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getCampusMapper(): CampusMapper
    {
        return $this->getUserMapper()->getJoinMapper('userCampus');
    }
}
