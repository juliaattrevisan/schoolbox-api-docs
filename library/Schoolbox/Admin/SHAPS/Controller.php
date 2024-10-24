<?php

/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\SHAPS;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Custom\Help\CourseParticipation;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

use function strpos;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;

    private CampusMapper $campusMapper;

    private RoleMapper $roleMapper;


    /**
     * A telephone to help.schoolbox.com.au so we can ask it things
     *
     * @var HelpRequest
     */
    private HelpRequest $helpRequest;


    /**
     * @param UserMapper      $userMapper
     * @param CampusMapper    $campusMapper
     * @param RoleMapper      $roleMapper
     * @param Auth\Parameters $authParams
     */
    public function __construct(
        UserMapper $userMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        Auth\Parameters $authParams
    ) {
        $this->userMapper   = $userMapper;
        $this->campusMapper = $campusMapper;
        $this->roleMapper   = $roleMapper;
        $this->helpRequest  = new HelpRequest($authParams, $this->userMapper);
    }

    /*
     * Helper method to get all staff in your Schoolbox instance.
     *
     * @return Collection
     */
    public function getAllStaff()
    {
        try {
            $provisionedUsers = $this->helpRequest->users();
            $enrolments       = $this->helpRequest->findEnrolments();
            foreach ($enrolments as $key => $enrolment) {
                $enrolments[$key]['participantIds'] = array_map('intval', array_column($enrolment['participants'], 'id'));
            }
        } catch (GuzzleException $e) {
            $message = $this->getReasonPhrase($e);
            $this->response = $this->responseAsJson(['error' => $message])->withStatus($e->getCode());
            return;
        }
        // All the staff in our Schoolbox instance
        $users = $this->userMapper->findByColumn('g.group_type', Role::STAFF, [
            'order' => [
                'lastname'  => 'asc',
                'firstname' => 'asc',
            ],
        ]);

        // append provisioned users who no longer exist
        $deletedUserIds = array_diff(array_keys($provisionedUsers), $users->getPropertyValues('id'));
        $deletedUsers   = $this->userMapper->findByColumn('id', $deletedUserIds, ['deleted' => true]);
        $users          = $users->merge($deletedUsers);

        $campusList = $this->campusMapper->findAll()->indexBy('id');
        $userList   = [];
        foreach ($users as $user) {
            $campuses = $user->getCampusCollection()->map(
                function (Campus $campus) use ($campusList) {
                    return $campusList->offsetGet($campus->getId())->getName();
                }
            )->getAll();
            $courses  = [];
            foreach ($enrolments as $enrolment) {
                if (in_array($user->getId(), $enrolment['participantIds'])) {
                    $courses[] = $enrolment['folderName'];
                }
            }
            $userList[] = [
                'id'          => $user->getId(),
                'firstname'   => $user->getFirstname(),
                'lastname'    => $user->getLastname(),
                'email'       => $user->getEmail(),
                'campuses'    => array_values($campuses),
                'role'        => $user->getRole()->getName(),
                'courses'     => $courses,
                'provisioned' => (int)array_key_exists($user->getId(), $provisionedUsers),
                'deleted'     => $user->isDeleted(),
            ];
        }

        $this->response = $this->responseAsJson($userList);
    }

    /**
     * Renders a list of all staff in your Schoolbox instance, and whether or not they are provisioned on Help.
     *
     * @throws RouteNotFound
     * @since 18.1
     * @api   /adminv2/shaps/provisioning
     */
    public function getAll(): void
    {
        $title  = 'Schoolbox Help Staff Provisioning';
        $errors = [];

        $campusList = $this->campusMapper->findAll()->sortBy('sequence', 'ASC');
        $campuses   = [];
        foreach ($campusList as $campus) {
            $campuses[] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }
        $roleList = $this->roleMapper->findByColumn('group_type', Role::STAFF)->sortBy('sequence', 'ASC');
        $roles    = [];
        foreach ($roleList as $role) {
            $roles[] = [
                'id'   => $role->getId(),
                'name' => $role->getName(),
            ];
        }

        $courses = [];
        try {
            $courseList = $this->helpRequest->courses();
            foreach ($courseList as $course) {
                $courses[] = [
                    'id'   => $course['folderId'],
                    'name' => $course['folderName'],
                ];
            }
        } catch (GuzzleException $e) {
            $errors[] = $this->getReasonPhrase($e);
        }

        $this->response = $this->responseFromTemplate(
            '@shaps/index.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('shaps.getAll')),
                'campuses'       => $campuses,
                'roles'          => $roles,
                'courses'        => $courses,
                'errors'         => $errors,
            ],
            $title
        );
    }

    /**
     * Post users to SHAPS
     *
     * @api   POST /adminv2/shaps/provision/
     * @since 181
     */
    public function postProvision(): void
    {
        $userIds      = $this->requestPost->get('users');
        $courses      = $this->requestPost->get('courses');
        $responseCode = 200;
        $messages     = [];

        try {
            $existingUsers = $this->helpRequest->users();
        } catch (GuzzleException $e) {
            $messages[] = 'Error during retrieval of users: ' . $this->getReasonPhrase($e);
            $existingUsers = [];
        }
        $emails = [];

        // Provision new users.
        $newUserIds = array_diff($userIds, array_keys($existingUsers));
        if (count($newUserIds) > 0) {
            $newUsers = $this->userMapper->findByColumn('id', $newUserIds);

            /** @var array{status: array, data: array} $responseUser */
            $responseUser = $this->helpRequest->provisionUsers($newUsers->getAll());

            /** @var array{code: int, message: string} $responseUserStatus */
            $responseUserStatus = $responseUser['status'];

            if ($responseUserStatus['code'] === 200) {
                $nUsersFailed = count($responseUser['data']); // Info on failures for specific users will be in $responseUser['data'].
                $nUsersSucceeded = $newUsers->count() - $nUsersFailed;
                $messages[] = "$nUsersSucceeded user" . ($nUsersSucceeded > 1 ? 's' : '') . ' provisioned successfully.';
                if ($nUsersFailed > 0) {
                    // Add info on failures for specific users to $messages, so this info will be seen by the user.
                    // See Custom/Help/CourseParticipation/Handler->postUserRegistrations().
                    $message = "$nUsersFailed user" . ($nUsersFailed > 1 ? 's' : '') . " not provisioned:\n";
                    foreach ($responseUser['data'] as $errorInfo) {
                        if (
                            count($errorInfo) !== 2 ||
                            !is_string($errorInfo[0])
                        ) {
                            $messages[] = "(user error info in unexpected format): " . var_export($errorInfo, true);
                        } else {
                            $errorDesc = $errorInfo[0];

                            /** @var array{id: string, firstname: string, lastname: string, email: string, title?: string} $userInfo */
                            $userInfo = $errorInfo[1];

                            $message .= "$errorDesc: {$userInfo['firstname']} {$userInfo['lastname']}, {$userInfo['email']}, userId:{$userInfo['id']}\n";
                        }
                    }
                    $messages[] = $message;
                }

                $emails = $newUsers->getPropertyValues('email');
            } else {
                $responseCode = $responseUserStatus['code'];
                if ($responseUserStatus['message'] !== '') {
                    $messages[] = $responseUserStatus['message'];
                }
                // The format of $responseUser['data'] is unknown in this situation, so cannot add it to $messages.
            }
        }

        // Gather usernames in response that correspond to user ids.
        foreach ($existingUsers as $existingUserId => $existingUserEmail) {
            if (in_array($existingUserId, $userIds)) {
                $emails[] = $existingUserEmail;
            }
        }

        // Course participation.
        if (!empty($courses) && !empty($emails)) {
            $courses = array_map('boolVal', $courses);

            /** @var array{status: array, data: array} $responseCourse */
            $responseCourse = $this->helpRequest->provisionUserParticipation($emails, $courses);

            /** @var array{code: int, message: string} $responseCourseStatus */
            $responseCourseStatus = $responseCourse['status'];

            if ($responseCourseStatus['code'] === 200) {
                $nUsersFailed = count($responseCourse['data']); // Info on failures for specific users will be in $responseUser['data'].
                $nUsersSucceeded = count($emails) - $nUsersFailed;
                $messages[] = "$nUsersSucceeded user's course participation updated successfully.";
                if ($nUsersFailed > 0) {
                    // Add info on failures for specific users to $messages, so this info will be seen by the user.
                    // Expect $responseCourse['data'] to contain an array of email addresses.
                    // See Custom/Help/CourseParticipation/Controller->postFolderAccessBySchool().
                    $message = "$nUsersFailed user's course participation not updated (see email addresses of those users below):\n";
                    foreach ($responseCourse['data'] as $emailAddress) {
                        $message .= " * $emailAddress\n";
                    }
                    $messages[] = $message;
                }
            } else {
                $responseCode = $responseCourseStatus['code'];
                if ($responseCourseStatus['message'] !== '') {
                    $messages[] = $responseCourseStatus['message'];
                }
                // The format of $responseUser['data'] is unknown in this situation, so cannot add it to $messages.
            }
        }

        // Build response.
        $this->response = $this->responseAsJson(
            [
                'data' => $messages,
            ]
        )->withStatus($responseCode);
    }

    /**
     * Returns a human-readable reason we can give to end-users to advise why their client's request was
     * unable to be processed by the SHAPs server
     *
     * @param GuzzleException $e
     * @return string
     */
    private function getReasonPhrase(GuzzleException $e): string
    {
        if ($e instanceof RequestException) {
            switch ($e->getCode()) {
                case CourseParticipation\Controller::STATUS_BAD_REQUEST:
                case CourseParticipation\Controller::STATUS_UNAUTHENTICATED:
                case CourseParticipation\Controller::STATUS_UNAUTHORISED:
                case CourseParticipation\Controller::STATUS_NOT_FOUND:
                case CourseParticipation\Controller::STATUS_SERVER_ERROR:
                    return $this->getResponseErrorMessage($e);
                default:
                    // ConnectException + TooManyRedirectsException
                    return "Unable to connect to Schoolbox HELP API - {$e->getMessage()}";
            }
        }
        // This should never happen. Storm just doesn't recognised that our custom exceptions are descendants of RequestException.
        return 'Unable to connect to Schoolbox HELP API';
    }


    /**
     * Returns the custom error message if one was sent by the server, otherwise falls back to the
     * default message for the exceptions HTTP response code
     *
     * @param RequestException $e
     * @return string
     */
    private function getResponseErrorMessage(RequestException $e): string
    {
        $response = $e->getResponse();
        if (!$response) {
            return $e->getMessage();
        }

        // The SHAPs API is JSON. If we got HTML, we're probably hitting somewhere random.
        $contentTypes = $response->getHeader('Content-Type');
        foreach ($contentTypes as $contentType) {
            if (0 === strpos($contentType, 'text/html')) {
                return 'Schoolbox Help API address appears misconfigured.';
            }
        }
        try {
            return $response->getBody()->getContents();
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }
}
