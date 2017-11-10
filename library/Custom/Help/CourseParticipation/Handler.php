<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Handler
{

    const REQUIRED_USER_FIELDS = [
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
     * @param UserAccessMapper          $userAccessMapper
     * @param SiteAccess\Mapper         $tokenMapper
     * @param Folder\Mapper             $folderMapper
     * @param User\Password\MailService $mailService
     * @throws Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper,
        User\Password\MailService $mailService
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper      = $tokenMapper;
        $this->folderMapper     = $folderMapper;
        $this->codeMapper       = $folderMapper->getJoinMapper('codes');
        $this->passwordMailService = $mailService;
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
        $roleId = $this->getRoleMapper()->findOneByColumn('name', Controller::PARTICIPANT_ROLE_NAME)->getId();

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

}
