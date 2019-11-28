<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Request;
use Custom\Help\CourseParticipation\Exception\ParticipantRolesNotFoundException;
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
    /* While these look like plain HTTP response codes, they are more than that.
     * Both the server and client apply semantics to them in addition to their inherent HTTP semantics.
     * Hence we need to enforce a single source-of-truth regarding their semantics so that the client and server
     * can both use them reliably.
     */
    public const STATUS_OK                    = 200;
    public const STATUS_NO_CONTENT            = 204; // Server successfully processed the request and is not returning any content.
    public const STATUS_MULTI_STATUS          = 207; // Server partially processed the request but errors occurred.
    public const STATUS_BAD_REQUEST           = 400; // Apparent client error. Eg missing method parameters. Includes if timestamp has expired.
    public const STATUS_UNAUTHENTICATED       = 401; // Authentication details not provided or incorrect.
    public const STATUS_UNAUTHORISED          = 403; // Authentication passed, but the request is not permitted.
    public const STATUS_NOT_FOUND             = 404; // The requested thing does not exist. Eg no schoolCode in 'site_access', no course area for school.
    public const STATUS_SERVER_ERROR          = 500; // Something went wrong server side while processing the request.

    /**
     * Client requests are considered unauthorised if they are older than this many seconds.
     */
    private const REQUEST_TIMESTAMP_EXPIRY = 10;

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
     * @param UserAccessMapper               $userAccessMapper
     * @param SiteAccess\Mapper              $tokenMapper
     * @param Folder\Mapper                  $folderMapper
     * @param User\Password\MailService      $mailService
     * @param string                         $portraitDir
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
     * @since 170
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
            $data['hashes'][$school] = $this->handler->computeAuthDigest($now->getUnixTimeStamp(), $school, $code->getAccessToken());
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@helpCourseParticipation/apitest.twig', $data));
    }


    /**
     * @api   GET /api/course-participation/{schoolCode:\w+}/digest
     * @since 170
     *
     * @param string $schoolCode
     */
    public function getRefreshDigest($schoolCode): void
    {
        $now = Alaress_Date_Format::now();

        try {
            $accessToken = $this->tokenMapper->findById($schoolCode)->getAccessToken();
        } catch (Mapper\Exception $e) {
            // bad request (we don't care about authentication/authorisation as this is Alaress-SU-only route)
            $this->sendGenericStatus(self::STATUS_NOT_FOUND, "No token registered for school code: {$schoolCode}");
            return;
        }

        $digest = $this->handler->computeAuthDigest($schoolCode, $now->getUnixTimeStamp(), $accessToken);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'ts'    => $now->getUnixTimeStamp(),
            'digest' => $digest
        ]));
    }

    /**
     * @api   POST /api/course-participation/apitest
     * @since 170
     */
    public function postApiTest()
    {
        $post = $this->getRequest()->post->getArrayCopy();

        $schoolCode = $post['schoolCode'];
        $accessToken = $this->tokenMapper->findById($schoolCode)->getAccessToken();

        $now = Alaress_Date_Format::now();

        $route = 'helpCourseParticipation.postUserRegistrations';
        $uri = $post['host'] . $this->getRouteIfAuthorized($route, [ 'schoolCode' => $schoolCode ]);

        $c = new Client();

        unset($post['schoolCode'], $post['host']);

        try {
            $c->post($uri, [
                'query' => [
                    'ts'    => $now->getUnixTimeStamp(),
                    'digest' => $this->handler->computeAuthDigest($now->getUnixTimeStamp(), $schoolCode, $accessToken)
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
     * @since 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     */
    public function getApiTestEnrolmentTable(string $schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if (!$this->isValidAuth($rq, $schoolCode)) {
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
     * // TODO. Add route description.
     *
     * @param string $schoolCode
     */
    public function getFolderAccessBySchool(string $schoolCode): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if (!$this->isValidAuth($rq, $schoolCode)) {
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
            $this->sendGenericStatus(self::STATUS_NOT_FOUND, "No course area found for school: {$schoolCode}");
            return;
        }
    }


    /**
     * @api   POST /api/course-participation/{schoolCode}/courses
     * @since 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function postFolderAccessBySchool(string $schoolCode): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if (!$this->isValidAuth($rq, $schoolCode)) {
            return;
        }

        $post = $rq->post;

        if (empty($post['course'])) {
            // empty payload: do nothing
            $this->sendGenericStatus(self::STATUS_BAD_REQUEST, "No 'course' parameter found");
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
                $this->sendGenericStatus(self::STATUS_UNAUTHORISED, "Folder {$folderId} does not belong to school '{$schoolCode}'");
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
            $rs->status->setCode(self::STATUS_MULTI_STATUS);
            $rs->content->set($this->buildJsonOutput(array_keys($failures)));
            return;
        }

        $this->sendGenericStatus(self::STATUS_NO_CONTENT);
    }


    /**
     * @api   GET /api/course-participation/{schoolCode}/users
     * @since 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     */
    public function getUsersBySchool(string $schoolCode): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if (!$this->isValidAuth($rq, $schoolCode)) {
            return;
        }

        $fuzzy = $rq->query->getBool('fuzzy', false);

        try{
            $users = $this->handler->getUsersBySchool($schoolCode, $fuzzy);
        } catch (ParticipantRolesNotFoundException $e) {
            $this->sendGenericStatus(self::STATUS_SERVER_ERROR);
            return;
        }

        $rs->content->set($this->buildJsonOutput($users));
    }


    /**
     * @api   POST /api/course-participation/{schoolCode}/users
     * @since 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     * @throws PDOException
     */
    public function postUserRegistrations(string $schoolCode): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        if (!$this->isValidAuth($rq, $schoolCode)) {
            return;
        }

        try {
            // check the server is correctly configured
            $this->getRoleMapper()->findOneByColumn('name', Handler::DEFAULT_PARTICIPANT_ROLE_NAME);
            $this->getCampusMapper()->findOneByColumn('id', Handler::PARTICIPANT_CAMPUS);
        } catch (Mapper\Exception $e) {
            // if this role doesn't exist, that's *bad* bad
            // No need to advise a client of the cause of this error.
            $this->sendGenericStatus(self::STATUS_SERVER_ERROR);
            return;
        }


        // process the payload
        $post = $rq->post;

        if (empty($post['user'])) {
            // empty payload: do nothing
            $this->sendGenericStatus(self::STATUS_BAD_REQUEST, "No 'user' parameter provided");
            return;
        }

        $byUsername = $rq->query->getBool('byUsername', false);

        $failedUsers = $this->handler->postUserRegistrations($schoolCode, $byUsername, $post);

        if (\count($failedUsers)) {
            // just blame the client :P
            $this->sendGenericStatus(self::STATUS_BAD_REQUEST);
            $rs->content->set($this->buildJsonOutput($failedUsers));

        } else {
            // everything went fine, nothing to respond with
            $this->sendGenericStatus(self::STATUS_NO_CONTENT);
        }
    }

    /**
     * @param string $schoolCode
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @return Collection|IFolder[]
     */
    private function getCoursesBySchool(string $schoolCode) : Collection
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
     * Returns whether the a request is authenticated and authorised to use the API
     *
     * Standard token authentication/authorisation for all routes in this controller.
     *
     * Business rules:
     * - Client is required to supply a school code, timestamp, and digest hash.
     * - If timestamp is more than 10 seconds old: return `400 Bad Request`
     * - If the school code given does not exist, return `404 Not Found`
     * - If the digest hash does not meet expectations based on the other 2 data, return `401 Forbidden`
     *
     * If not, also sets up a JSON-formatted response.
     *
     * @param Request $request
     * @param string $schoolCode
     * @return bool
     */
    private function isValidAuth(Request $request, string $schoolCode): bool
    {
        // 1. Check we have all the pieces we need
        $missing = [];
        if (null === $request->headers->get('Authentication')) {
            $missing[] = 'Authentication';
        }
        if (null === $request->query->get('ts')) {
            $missing[] = 'ts';
        }
        if (\count($missing)) {
            $message = implode(',', $missing);
            $this->sendGenericStatus(self::STATUS_BAD_REQUEST, "Missing authentication parameters: [{$message}]");
            return false;
        }

//        // 2. Check the request's timestamp
        $requestTimestamp = Alaress_Date_Format::newFromTimestamp($request->query->get('ts', 0));

        // 3. Ensure the route is valid (ie the school-code exists)
        try {
            $accessToken = $this->tokenMapper->findById($schoolCode)->getAccessToken();
        } catch (Mapper\Exception $e) {
            $this->sendGenericStatus(self::STATUS_NOT_FOUND, "No school found for school code '{$schoolCode}'");
            return false;
        }

        // 4. Check the requester is who they say they are

        $authDigest     = $request->headers->get('Authentication');
        $expectedDigest = $this->handler->computeAuthDigest($requestTimestamp->getUnixTimeStamp(), $schoolCode, $accessToken);
        if ($authDigest !== $expectedDigest) {
            $this->sendGenericStatus(self::STATUS_UNAUTHENTICATED, 'Incorrect API key and/or school code');
            return false;
        }

        return true;
    }


    /**
     * Prepares a consistent JSON response.
     *
     * Optionally supports a reason phrase to distinguish different cases that may give the same response code.
     *
     * @param int         $code
     * @param null|string $reason
     * @return void
     */
    private function sendGenericStatus(int $code, ?string $reason = null): void
    {
        $r = $reason
            ? new Response($code, [], null, '1.1', $reason)
            : new Response($code);

        $this->getResponse()->status->set($r->getStatusCode());

        $content = $code === self::STATUS_NO_CONTENT
            ? null
            : $this->buildJsonOutput($r->getReasonPhrase());

        $this->getResponse()->content->set($content);
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
