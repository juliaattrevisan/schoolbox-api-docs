<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\ProfessionalDevelopment;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\RoleMapper;
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
     * @var ProfessionalDevelopment\Mapper
     */
    private $pdMapper;


    /**
     * @param UserAccessMapper               $userAccessMapper
     * @param SiteAccess\Mapper              $tokenMapper
     * @param Folder\Mapper                  $folderMapper
     * @param ProfessionalDevelopment\Mapper $pdMapper
     * @param User\Password\MailService      $mailService
     * @param string                         $portraitDir
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper,
        ProfessionalDevelopment\Mapper $pdMapper,
        User\Password\MailService $mailService,
        $portraitDir
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper = $tokenMapper;
        $this->folderMapper = $folderMapper;
        $this->codeMapper = $folderMapper->getJoinMapper('codes');
        $this->passwordMailService = $mailService;
        $this->pdMapper = $pdMapper;

        $portraitDir = realpath($portraitDir);

        if (!$portraitDir) {
            throw new InvalidArgumentException('Invalid portrait directly given; is it readable?');
        }

        $this->portraitDir = $portraitDir;
    }


    /**
     * @param string $schoolCode
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @return array
     */
    public function getFolderAccessBySchool($schoolCode)
    {
        // ok, if you're here, you can see the thing.
        $courses = $this->getCoursesBySchool($schoolCode);

        $payload = [];

        foreach ($courses as $course) {
            $participants = $this->userAccessMapper->findUsersInCategory($course)->getColumnValues('email');

            $payload[] = [
                'folderName'   => $course->getName(),
                'folderId'     => $course->getId(),
                'participants' => array_filter($participants)
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
    public function getUsersBySchool($schoolCode, $fuzzy = false)
    {
        $roleId = $this->getRoleMapper()->findOneByColumn('name', self::PARTICIPANT_ROLE_NAME)->getId();

        // show the list of users for this school
        // filter to the list of users whose external IDs are prefixed with the given school code
        $predicate = $this->getUserMapper()->getPredicateBuilder()->equals('group_id', $roleId);

        if ($fuzzy) {
            // if we're doing a fuzzy lookup, search by `year`
            $map = json_decode(file_get_contents(__DIR__ . '/code-map.json'), true);

            if (empty($map[$schoolCode])) {
                // we don't have this code mapped, therefore nobody matches
                return [];
            }

            $predicate->equals('year', $map[$schoolCode]);

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
    public function postUserRegistrations($schoolCode, $byUsername, iterable $post)
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

                /* Add Professional Development data
                 * Create a new one if it doesn't already exist
                 */
                /** @var ProfessionalDevelopment\Model $pd */
                try {
                    $pd = $this->pdMapper->findOneByColumn('user_id', $user->getId());
                }
                catch (Mapper\Exception $e) {
                    $pd = $this->pdMapper->createModel();
                    $pd->setUser($user);
                }

                $pd->setNumber($userData['pdNumber']);
                $pd->setRegistrationState($userData['pdState']);

                $validation = new ProfessionalDevelopment\ValidProfessionalDevelopment($this->pdMapper->getStates());

                /* Only save if valid data exists; otherwise noop
                 * PD data is optional to provide
                 */
                if ($validation->isSatisfiedBy($pd)) {
                    $this->pdMapper->save($pd);
                }

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
     * Compute an access token (digest auth) for the given school code and timestamp.
     *
     * @param string               $schoolCode
     * @param Alaress_Date_Format $now
     * @throws Mapper\Exception
     * @return string
     */
    public function computeAuthDigest($schoolCode, Alaress_Date_Format $now)
    {
        $privateKey = $this->tokenMapper->findById($schoolCode)->getAccessToken();

        return $this->computeAccessToken($now, $schoolCode, $privateKey);
    }


    /**
     * Mash ingredients thoroughly in a small bowl, seasoning with a little salt to taste.
     *
     * @param Alaress_Date_Format $timestamp
     * @param string              $schoolCode
     * @param string              $privateKey
     * @return string
     */
    public function computeAccessToken(Alaress_Date_Format $timestamp, $schoolCode, $privateKey)
    {
        return sha1(sprintf(':%s-%s-%s:', $timestamp, $schoolCode, $privateKey));
    }


    /**
     * @param string|int $externalId
     * @return bool|string
     */
    private function getPortraitPath($externalId)
    {
        return "{$this->portraitDir}/{$externalId}.jpg";
    }


    /**
     * @param string $schoolCode
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @return Collection|IFolder[]
     */
    private function getCoursesBySchool($schoolCode)
    {
        // ok, if you're here, you can see the thing.
        $folder = $this->folderMapper->findById(
            $this->codeMapper->findOneByColumn('code', $schoolCode)->getFolder()->getId()
        );

        // obtain all direct descendants of this folder
        // the scope of implementation is immediate children only, rather than attempting to gather all descendants
        return $this->folderMapper->findByParentId($folder->getId());
    }


    /**
     * @param array $userData
     * @return bool
     */
    private function userPayloadIsValid(array $userData)
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
    private function getUserMapper()
    {
        return $this->userAccessMapper->getJoinMapper('user');
    }


    /**
     * @return RoleMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getRoleMapper()
    {
        return $this->getUserMapper()->getJoinMapper('role');
    }


    /**
     * @return CampusMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getCampusMapper()
    {
        return $this->getUserMapper()->getJoinMapper('userCampus');
    }
}
