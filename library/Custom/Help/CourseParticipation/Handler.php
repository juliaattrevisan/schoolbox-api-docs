<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\RoleMapper;
use Schoolbox\SHAPS\AuthDigest;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;


class Handler
{
    const PARTICIPANT_ROLE_NAME = 'School Staff';

    const PARTICIPANT_CAMPUS    = 1;
    const REQUIRED_USER_FIELDS  = [
        'id', 'email', 'title', 'firstname', 'lastname'
    ];

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
     * @var string
     */
    private $portraitDir;


    /**
     * @param UserAccessMapper               $userAccessMapper
     * @param SiteAccess\Mapper              $tokenMapper
     * @param Folder\Mapper                  $folderMapper
     * @param User\Password\MailService      $mailService
     * @param string                         $portraitDir
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper,
        User\Password\MailService $mailService,
        string $portraitDir
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper = $tokenMapper;
        $this->folderMapper = $folderMapper;
        $this->codeMapper = $folderMapper->getJoinMapper('codes');
        $this->passwordMailService = $mailService;

        $portraitDir = realpath($portraitDir);

        if (!$portraitDir) {
            throw new InvalidArgumentException('Invalid portrait directly given; is it readable?');
        }

        $this->portraitDir = $portraitDir;
    }


    /**
     * @param string $schoolCode
     * @param bool $fuzzy
     * @return array
     */
    public function getFolderAccessBySchool(string $schoolCode, bool $fuzzy=false) : array
    {
        // ok, if you're here, you can see the thing.
        $courses = $this->getCoursesBySchool($schoolCode);

        $payload = [];

        foreach ($courses as $course) {
            // TODO we need return not just the ID but also the firstname and lastname so we can show prospects
            $participantIds = $this->userAccessMapper->findUsersInCategory($course)->getPropertyValues('externalId');

            for ($i = 0; $i < count($participantIds); $i++) {
                if ($fuzzy) {
                    // if we're doing a fuzzy lookup, search by `year`
                    /** @var string[][] $map */
                    $map = json_decode(file_get_contents(__DIR__ . '/code-map.json'), true);

                    if (empty($map[$schoolCode])) {
                        // we don't have this code mapped, therefore nobody matches
                    }

                    // TODO how do we handle this weird case
                } else {
                    // "normal" behaviour is to look up by external IDs
                    $matches = "";
                    preg_match("/" . preg_quote($schoolCode) . "\.(\d*)/", $participantIds[$i], $matches);
                    if (! empty($matches)) {
                        $participantIds[$i] = $matches[1];
                    } else {
                        // This user didn't have a school code, so don't include them
                        $participantIds[$i] = null;
                    }
                }
            }

            $payload[] = [
                'folderName'   => $course->getName(),
                'folderId'     => $course->getId(),
                'participants' => array_filter($participantIds)
            ];
        }

        return $payload;
    }


    /**
     * @param string $schoolCode
     * @param bool   $fuzzy
     * @return array
     * @throws Mapper\DefinitionException
     */
    public function getUsersBySchool(string $schoolCode, bool $fuzzy = false) : array
    {
        // TODO this probably needs to search for all staff types (including administration)
        $roleId = $this->getRoleMapper()->findOneByColumn("name", self::PARTICIPANT_ROLE_NAME)->getId();

        // show the list of users for this school
        // filter to the list of users whose external IDs are prefixed with the given school code
        $predicate = $this->getUserMapper()->getPredicateBuilder()->equals('group_id', $roleId);

        if ($fuzzy) {
            // if we're doing a fuzzy lookup, search by `year`
            /** @var string[][] $map */
            $map = json_decode(file_get_contents(__DIR__ . '/code-map.json'), true);

            if (empty($map[$schoolCode])) {
                // we don't have this code mapped, therefore nobody matches
                return [];
            }

            $predicate->in('year', $map[$schoolCode]);

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
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     */
    public function postUserRegistrations(string $schoolCode, bool $byUsername, iterable $post) : array
    {
        $participantRole     = $this->getRoleMapper()->findOneByColumn('name', self::PARTICIPANT_ROLE_NAME);
        $participantCampuses = $this->getCampusMapper()->findByColumn('id', self::PARTICIPANT_CAMPUS);

        $failedUsers = [];

        foreach ($post['user'] as $userData) {
            if (!$this->userPayloadIsValid($userData)) {
                $failedUsers[] = [
                    'Invalid payload', $userData
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

                // cache the old values
                $oldUsername   = $user->getUsername();
                $oldExternalId = $user->getExternalId();

            } catch (Mapper\Exception\Deleted $e) {
                // user exists, but is deleted; consider this a fail case
                $failedUsers[] = [
                    'User is deleted', $userData
                ];
                continue;

            } catch (Mapper\Exception $e) {
                // user does not exist; create the model
                $user = $this->getUserMapper()->createModel()
                    ->setRole($participantRole)
                    ->setCampusCollection($participantCampuses)
                    ->setLocalEmail(false)
                    ->setWebmail(false)
                    ->setUseInternalMail(false)
                    ->setEportfolioEnable(false)
                ;

                // no previous data
                $oldUsername   = '';
                $oldExternalId = '';
            }

            // update user object
            $user
                ->setUsername($userData['email']) // yes, email -> username is on purpose
                ->setTitle($userData['title'])
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

                if ($oldExternalId !== $externalId) {
                    // the external ID has changed, so we need to change the filename of the profile pic (if it exists)
                    if (is_readable($this->getPortraitPath($oldExternalId))) {
                        $renameSuccess = rename(
                            $this->getPortraitPath($oldExternalId),
                            $this->getPortraitPath($externalId)
                        );

                        if (!$renameSuccess) {
                            // ... where do I log this failure? :|
                        }
                    }
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
    public function computeAuthDigest(int $timestamp, string $schoolCode, string $accessToken) : string
    {
        return (new AuthDigest($schoolCode, $accessToken))->generate($timestamp);
    }


    /**
     * @param string|int $externalId
     * @return string
     */
    private function getPortraitPath($externalId) : string
    {
        return "{$this->portraitDir}/{$externalId}.jpg";
    }


    /**
     * @param string $schoolCode
     * @return Collection|IFolder[]
     */
    private function getCoursesBySchool(string $schoolCode) : Collection
    {
        // ok, if you're here, you can see the thing.
        try {
            $code = $this->codeMapper->findOneByColumn('code', $schoolCode)->getFolder()->getId();
            $folder = $this->folderMapper->findById($code);
            // obtain all direct descendants of this folder
            // the scope of implementation is immediate children only, rather than attempting to gather all descendants
            return $this->folderMapper->findByParentId($folder->getId());
        } catch (Mapper\Exception $e) {
            // No courses? Do nothing, we're going to return an empty collection outside this block
        }

        return new Collection();
    }


    /**
     * @param array $userData
     * @return bool
     */
    private function userPayloadIsValid(array $userData) : bool
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
    private function getUserMapper() : UserMapper
    {
        return $this->userAccessMapper->getJoinMapper('user');
    }


    /**
     * @return RoleMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getRoleMapper() : RoleMapper
    {
        return $this->getUserMapper()->getJoinMapper('role');
    }


    /**
     * @return CampusMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getCampusMapper() : CampusMapper
    {
        return $this->getUserMapper()->getJoinMapper('userCampus');
    }
}
