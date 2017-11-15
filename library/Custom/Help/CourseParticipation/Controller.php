<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Request\Values;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use PDOException;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
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
     * @var Handler
     */
    private $handler;


    /**
     * @param UserAccessMapper          $userAccessMapper
     * @param SiteAccess\Mapper         $tokenMapper
     * @param Folder\Mapper             $folderMapper
     * @param User\Password\MailService $mailService
     * @param string                    $portraitDir
     * @throws Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper,
        User\Password\MailService $mailService,
        $portraitDir
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper      = $tokenMapper;
        $this->folderMapper     = $folderMapper;
        $this->codeMapper       = $folderMapper->getJoinMapper('codes');

        // TODO rejig for DI
        $this->handler = new Handler(
            $this->userAccessMapper,
            $this->tokenMapper,
            $this->folderMapper,
            $mailService,
            $portraitDir
        );
    }


    /**
     * @api   GET /api/course-participation/apitest
     * @level 170
     *
     * @throws ServiceNotFound
     */
    public function getApiTest()
    {
        /** @var SiteAccess\Model[] $codes */
        $codes = $this->tokenMapper->findAll();

        $now = Alaress_Date_Format::now();

        $data = [
            'codes'  => $codes,
            'ts'     => $now->getUnixTimeStamp(),
            'hashes' => [],
            'fields' => Handler::REQUIRED_USER_FIELDS,
        ];

        foreach ($codes as $code) {
            $school = $code->getSiteSchoolCode();
            $data['hashes'][$school] = $this->handler->computeAccessToken($now, $school, $code->getAccessToken());
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@helpCourseParticipation/apitest.twig', $data));
    }


    /**
     * @api   GET /api/course-participation/{schoolCode:\w+}/token
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function refreshToken($schoolCode)
    {
        $now = Alaress_Date_Format::now();

        try {
            $token = $this->handler->computeAuthDigest($schoolCode, $now);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'ts'    => $now->getUnixTimeStamp(),
                'token' => $token
            ]));

        } catch (\Schoolbox\Mapper\Exception|Mapper\DefinitionException $e) {
            // bad request
            $this->sendGenericStatus(400);
        }
    }

    /**
     * @api   POST /api/course-participation/apitest
     * @level 170
     */
    public function postApiTest()
    {
        $post = $this->getRequest()->post->getArrayCopy();

        $school = $post['schoolCode'];

        $now = Alaress_Date_Format::now();

        $route = 'helpCourseParticipation.postUserRegistrations';
        $uri = $post['host'] . $this->getRouteIfAuthorized($route, [ 'schoolCode' => $school ]);

        $c = new Client();

        unset($post['schoolCode'], $post['host']);

        try {
            $c->post($uri, [
                'query' => [
                    'ts'    => $now->getUnixTimeStamp(),
                    'token' => $this->handler->computeAuthDigest($school, $now)
                ],
                'form_params' => $post
            ]);

            $this->getResponse()->redirect->afterPost(
                $this->getRouteIfAuthorized('helpCourseParticipation.getApiTest')
            );

        } catch (BadResponseException $e) {
            $response = $e->getResponse();

            $content = $this->buildFileOutputFromString($response->getBody()->getContents());

            $this->getResponse()->status->set($response->getStatusCode());
            $this->getResponse()->content->set($content);
            $this->getResponse()->headers->set('Content-Type', 'text/html');
        }
    }


    /**
     * @api   GET /api/course-participation/ecc
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function getApiTestEnrolmentTable($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if ($this->isInvalidAuth($rq->query, $schoolCode)) {
            return;
        }

        $registeredUsers = $this->handler->getUsersBySchool($schoolCode);

        $enrolments = $this->handler->getFolderAccessBySchool($schoolCode);

        $rs->content->set($this->buildUnthemedHtmlOutput('@helpCourseParticipation/apitest-usertable.twig', [
            'courses' => $enrolments,
            'users'   => $registeredUsers
        ]));
    }


    /**
     * @param string $schoolCode
     * @throws ServiceNotFound
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\DefinitionException
     */
    public function getFolderAccessBySchool($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if ($this->isInvalidAuth($rq->query, $schoolCode)) {
            return;
        }

        // ok, if you're here, you can see the thing.
        try {
            $rs->content->set(
                $this->buildJsonOutput(
                    $this->handler->getFolderAccessBySchool($schoolCode)
                )
            );
        } catch (Mapper\Exception $e) {
            $this->sendGenericStatus(404);
            return;
        }
    }


    /**
     * @api   POST /api/course-participation/{schoolCode}/courses
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function postFolderAccessBySchool($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if ($this->isInvalidAuth($rq->query, $schoolCode)) {
            return;
        }

        $post = $rq->post;

        if (empty($post['course'])) {
            // empty payload: do nothing
            $this->sendGenericStatus(400);
            return;
        }

        $courses   = $this->getCoursesBySchool($schoolCode);
        $courseIds = $courses->getColumnValues('id');

        $failures = [];

        /*
         * Business rule: POST payload is expected to contain a data struct that provides:
         *   - for a particular folder ID
         *       - a list of email addresses to enrol into that folder
         *       - a list of email addresses to unenrol from that folder
         */

        foreach ($post['course'] as $folderId => $enrolments) {
            if (!in_array($folderId, $courseIds)) {
                /*
                 * Business rule: A request that specifies folder IDs that do not belong to the school given returns 403
                 */
                $this->sendGenericStatus(403);
                return;
            }

            foreach ($enrolments as $emailAddress => $enrol) {
                try {
                    $user = $this->getUserMapper()->findOneByColumn('username', $emailAddress);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $failures[$emailAddress] = true;
                    continue;
                }

                if ($enrol) {
                    // add this user to this folder
                    $this->userAccessMapper->create(
                        $this->userAccessMapper->createModel()
                            ->setUser($user)
                            ->setAccessLevel(UserAccess::ACCESS_READ)
                            ->setService(UserAccess::SERVICE_HOMEPAGE)
                            ->setServiceIdentifier($folderId)
                    );
                } else {
                    // remove this user from this folder
                    $this->userAccessMapper->deleteByColumnList([
                        'uid' => $user->getId(),
                        'service' => UserAccess::SERVICE_HOMEPAGE,
                        'sid' => $folderId,
                    ]);
                }
            }
        }

        if ($failures) {
            $rs->status->setCode(207);
            $rs->content->set($this->buildJsonOutput(array_keys($failures)));
            return;
        }

        $this->sendGenericStatus(204);
    }


    /**
     * @api   GET /api/course-participation/{schoolCode}/users
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     */
    public function getUsersBySchool($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if ($this->isInvalidAuth($rq->query, $schoolCode)) {
            return;
        }

        $fuzzy = $rq->query->getBool('fuzzy', false);

        $rs->content->set(
            $this->buildJsonOutput(
                $this->handler->getUsersBySchool($schoolCode, $fuzzy)
            )
        );
    }


    /**
     * @api   POST /api/course-participation/{schoolCode}/users
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws PDOException
     */
    public function postUserRegistrations($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        try {
            // check the server is correctly configured
            $this->getRoleMapper()->findOneByColumn('name', Handler::PARTICIPANT_ROLE_NAME);
            $this->getCampusMapper()->findByColumn('id', Handler::PARTICIPANT_CAMPUS);

        } catch (Mapper\Exception $e) {
            // if this role doesn't exist, that's *bad* bad
            $this->sendGenericStatus(500);
            return;
        }

        if ($this->isInvalidAuth($rq->query, $schoolCode)) {
            return;
        }

        // process the payload
        $post = $rq->post;

        if (empty($post['user'])) {
            // empty payload: do nothing
            $this->sendGenericStatus(400);
            return;
        }

        $byUsername = $rq->query->getBool('byUsername', false);

        $failedUsers = $this->handler->postUserRegistrations($schoolCode, $byUsername, $post);

        if (\count($failedUsers)) {
            // just blame the client :P
            $this->sendGenericStatus(400);
            $rs->content->set($this->buildJsonOutput($failedUsers));

        } else {
            // everything went fine, nothing to respond with
            $this->sendGenericStatus(204);
        }
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
     * @param Values $query
     * @param string $schoolCode
     * @return bool
     */
    private function isInvalidAuth(Values $query, $schoolCode)
    {
        $requestTimestamp = new Alaress_Date_Format($query->get('ts', 0), Alaress_Date_Format::TIMESTAMP);

        $authDigest = $query->get('token', '');

        $responseCode = $this->getResponseCode($schoolCode, $requestTimestamp, $authDigest);

        if ($responseCode === 200) {
            return false;
        }

        // if the request didn't pass the gauntlet, bounce the request
        $this->sendGenericStatus($responseCode);
        return true;
    }


    /**
     * Standard token auth for all routes in this controller.
     *
     * Business rules:
     * - Client is required to supply a school code, timestamp, and digest hash.
     * - If timestamp is more than 10 seconds old: return `400 Bad Request`
     * - If the school code given does not exist, return `404 Not Found`
     * - If the digest hash does not meet expectations based on the other 2 data, return `403 Forbidden`
     *
     * @param string              $schoolCode
     * @param Alaress_Date_Format $requestTimestamp
     * @param string              $authDigest
     * @return int
     */
    private function getResponseCode($schoolCode, Alaress_Date_Format $requestTimestamp, $authDigest)
    {
        $deltaTimestamp = abs(Alaress_Date_Format::now()->diffInSeconds($requestTimestamp));

        // permit a window of +/- 10 seconds to guard against replay
        if ($deltaTimestamp > 10) {
            return 400;
        }

        try {
            $privateKey = $this->tokenMapper->findById($schoolCode)->getAccessToken();
        } catch (Mapper\Exception $e) {
            return 404;
        }

        try {
            $expectedToken = $this->handler->computeAccessToken(
                $requestTimestamp,
                $schoolCode,
                $privateKey
            );
        } catch (Mapper\DefinitionException $e) {
            return 500;
        }

        if ($expectedToken !== $authDigest) {
            return 403;
        }

        return 200;
    }


    /**
     * @param $code
     * @return void
     */
    private function sendGenericStatus($code)
    {
        $r = new Response($code);

        $this->getResponse()->status->set($r->getStatusCode());

        $content = $code === 204 ? null : $this->buildJsonOutput($r->getReasonPhrase());

        $this->getResponse()->content->set($content);
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
