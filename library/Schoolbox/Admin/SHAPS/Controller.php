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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Custom\Help\CourseParticipation;
use InvalidArgumentException;

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
            $this->response->status->set($e->getCode());
            $this->response->content->set($this->buildJsonOutput(['error' => $message]));
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

        $this->getResponse()->content->set($this->buildJsonOutput($userList));
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

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/index.twig',
                [
                    'pageCurrent'    => $this->router->generate('shaps.getAll'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                    'campuses'       => $campuses,
                    'roles'          => $roles,
                    'courses'        => $courses,
                    'errors'         => $errors,
                ]
            )->setTitle($title)
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
        $userIds      = $this->request->post->get('users');
        $courses      = $this->request->post->get('courses');
        $responseCode = 200;
        $messages     = [];

        // data, status => code, message
        try {
            $existingUsers = $this->helpRequest->users();
        } catch (GuzzleException $e) {
            $existingUsers = [];
        }
        $newUserIds = array_diff($userIds, array_keys($existingUsers));
        if (count($newUserIds) > 0) {
            $responseUser = $this->helpRequest->provisionUsers($newUserIds);
            $responseCode = $responseUser['status']['code'];
            if ($responseCode === 200) {
                $messages[] = count($newUserIds) . ' user' . (count($newUserIds) > 1 ? 's' : '') . ' provisioned successfully';
            } else {
                $messages = array_column($responseUser['data'], 'message');
            }
        }

        if (!empty($courses)) {
            $courses            = array_map('boolVal', $courses);
            $responseCourse     = $this->helpRequest->provisionUserParticipation($userIds, $courses);
            $responseCourseCode = $responseCourse['status']['code'];
            if ($responseCourseCode === 200) {
                $messages[] = 'Course participation updated successfully.';
            } else {
                $responseCode = $responseCourseCode;
                $messages     = array_merge($messages, array_column($responseCourse['data'], 'message'));
            }
        }

        // Build response
        $this->response->status->set($responseCode);
        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'data' => $messages,
                ]
            )
        );
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
