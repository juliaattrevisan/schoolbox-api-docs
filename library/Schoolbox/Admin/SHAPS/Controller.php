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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Custom\Help\CourseParticipation;
use InvalidArgumentException;

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
    private $helpRequest;


    /**
     * Controller constructor.
     *
     * @param UserMapper      $userMapper
     * @param Auth\Parameters $authParams
     */
    public function __construct(
        UserMapper $userMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        Auth\Parameters $authParams
    )
    {
        $this->userMapper  = $userMapper;
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->helpRequest = new HelpRequest($authParams, $this->userMapper);
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
            $enrolements = $this->helpRequest->findEnrolments();
        } catch (GuzzleException $e) {
            $provisionedUsers = [];
            $enrolements = [];

        }
        // All the staff in our Schoolbox instance
        $users = $this->userMapper->findByColumn('g.group_type', \Schoolbox\Role::STAFF, [
            'order' => [
                'lastname' => 'asc',
                'firstname' => 'asc'
            ]
        ]);
        $campusList = $this->campusMapper->findAll()->indexBy('id');
        $userList = [];
        foreach ($users as $user) {
            $campuses = $user->getCampusCollection()->map(
                function (Campus $campus) use ($campusList) {
                    return $campusList->offsetGet($campus->getId())->getName();
                }
            )->getAll();
            $courses = [];
            foreach ($enrolements as $enrolement) {
                if (in_array($user->getId(), $enrolement['participants'])) {
                    $courses[$enrolement['folderId']] = $enrolement['folderName'];
                }
            }
            $userList[] = [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname'  => $user->getLastname(),
                'external_id' => $user->getExternalId(),
                'campuses' => array_values($campuses),
                'role'     => $user->getRole()->getName(),
                'courses'  => $courses,
                'provisioned' => (int)array_key_exists($user->getId(), $provisionedUsers)
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput($userList));
    }

    /**
     * Constructs an array of field names based on whether the field was empty.
     * TODO factor this out into a common location; currently the fields are hardcoded separately here
     * and in library/Custom/Help/CourseParticipation/Handler.php
     * Also, this solution does not work for the ajax search page, as it is not asynchronous.
     *
     * @param \Schoolbox\User $user
     * @return array
     */
    private function getInvalidities(\Schoolbox\User $user)
    {
        $errors = [];
        if (empty($user->getId())) {
            $errors[] = 'id';
        }
        if (empty($user->getEmail())) {
            $errors[] = 'email';
        }
        if (empty($user->getFirstname())) {
            $errors[] = 'firstname';
        }
        if (empty($user->getLastname())) {
            $errors[] = 'lastname';
        }
        return $errors;
    }


    /**
     * Renders a list of all staff in your Schoolbox instance, and whether or not they are provisioned on Help.
     *
     * @api   /adminv2/shaps/provisioning
     * @since 18.1
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getAll(): void
    {
        $title = 'Schoolbox Help Staff Provisioning';

//        try {
//            $provisionedUsers = $this->helpRequest->findProvisionedUsers();
//        } catch (GuzzleException $e) {
//            $this->getResponse()->content->set(
//                $this->buildHtmlOutput('@shaps/provisioning/index.twig', [
//                    'pageCurrent' => $this->router->generate('shaps.getAll'),
//                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
//                    'errorMessage' => $this->getReasonPhrase($e)
//                ])->setTitle($title)
//            );
//            return;
//        }
//
//        $errors = [];
//        $allStaff = $this->getAllStaff();
//        foreach ($allStaff as $user) {
//            $errors[$user->getId()] = $this->getInvalidities($user);
//        }
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
                    'id' => $course['folderId'],
                    'name' => $course['folderName'],
                ];
            }
        } catch (GuzzleException $e) {
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/index.twig',
                [
                'pageCurrent' => $this->router->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                'campuses'       => $campuses,
                'roles'          => $roles,
                'courses'        => $courses,
                ]
            )->setTitle($title)
        );
    }




    /**
     * Renders a list of staff in your Schoolbox instance that are also provisioned on Help.
     *
     * @api GET /adminv2/shaps/provisioning/provisioned
     * @since 18.1
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     */
    public function getProvisioned(): void
    {
        $title = 'Provisioned Staff - Schoolbox Help Staff Provisioning';

        try {
            $provisionedUsers = $this->helpRequest->findProvisionedUsers();
        } catch (GuzzleException $e) {
            $this->getResponse()->content->set(
                $this->buildHtmlOutput('@shaps/provisioning/provisioned.twig', [
                    'pageCurrent' => $this->router->generate('shaps.getAll'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                    'errorMessage' => $this->getReasonPhrase($e),
                ])->setTitle($title)
            );
            return;
        }

        $allStaff = [];
        $errors = [];
        foreach ($allStaff as $user) {
            $errors[$user->getId()] = $this->getInvalidities($user);
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/provisioned.twig',
                [
                'pageCurrent' => $this->router->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                'allUsers' => $provisionedUsers,
                'provisionedUsers' => $provisionedUsers,
                'errors' => $errors,
                'errorMessage' => null,
                ]
            )->setTitle($title)
        );
    }

    /**
     * Renders a list of staff in your Schoolbox instance that are NOT provisioned on Help.
     *
     * @api GET /adminv2/shaps/provisioning/unprovisioned
     * @since 18.1
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     */
    public function getUnprovisioned(): void
    {
        $title = 'Unprovisioned Staff - Schoolbox Help Staff Provisioning';

        try {
            $provisionedUsers = $this->helpRequest->findProvisionedUsers();
        } catch (GuzzleException $e) {
            $this->getResponse()->content->set(
                $this->buildHtmlOutput('@shaps/provisioning/unprovisioned.twig', [
                    'pageCurrent' => $this->router->generate('shaps.getAll'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                    'errorMessage' => $this->getReasonPhrase($e),
                ])->setTitle($title)
            );
            return;
        }

        $allStaff = []; //$this->getAllStaff();
        $errors = [];
        foreach ($allStaff as $user) {
            $errors[$user->getId()] = $this->getInvalidities($user);
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/unprovisioned.twig',
                [
                'pageCurrent' => $this->router->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                'allUsers' => $allStaff->diff($provisionedUsers),
                'errors' => $errors,
                'errorMessage' => null,
                ]
            )->setTitle($title)
        );
    }

    /**
     * Renders a selectize for searching users in your Schoolbox instance to see if they are provisioned.
     *
     * @api GET /adminv2/shaps/provisioning/search
     * @since 18.1
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getUserProvisioningSearch(): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/search.twig',
                [
                'pageCurrent' => $this->router->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                'allUsers' => [],
                'errorMessage' => null,
                ]
            )->setTitle('Search - Schoolbox Help Staff Provisioning')
        );
    }


    /**
     * Provides details about a user in response to an ajax request for that user's external ID.
     * Selectize gives us the user's Id
     *
     * @api   GET /adminv2/shaps/provisioning/{userId}
     * @since 18.1
     *
     * @param string $userId
     * @throws \InvalidArgumentException
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function getSearchUsers(string $userId): void
    {
        $user = $this->userMapper->findById($userId);
        $errors[$user->getId()] = $this->getInvalidities($user);

        try {
            $provisionedUsers = $this->helpRequest->findProvisionedUsers();
        } catch (GuzzleException $e) {
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'pageCurrent' => $this->router->generate('shaps.getAll'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
                'errorMessage' => $this->getReasonPhrase($e),
            ]));

            return;
        }

        // Check if this user's id was in the provisionedUsers index - don't need to use in_array to test
        $exists = $provisionedUsers->offsetGet($user->getId()) !== null;

        $userJSON = [
            'name' => $user->getFullName(),
            'provisioned' => $exists,
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
            'errorList' => $errors,
            'errorMessage' => null,
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($userJSON));
    }

    /**
     * Renders a list of all courses available for your school on Help, and the users enrolled in each.
     *
     * @api GET /adminv2/shaps/courses
     * @since 18.1
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     */
    public function getUsersInCourses(): void
    {
        $errorMessage = null;
        try {
            $courses = $this->helpRequest->findEnrolments();
        } catch (GuzzleException $e) {
            $courses      = [];
            $errorMessage = $this->getReasonPhrase($e);
        }
        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/courses/index.twig', [
            'pageCurrent' => $this->router->generate('shaps.getAll'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
            'errorMessage' => $errorMessage,
            'courses' => $courses,
            'serverUrl' => $this->helpRequest->getServerUrl(),
        ])->setTitle('Schoolbox Help Course Enrolments'));
    }

    /**
     * Renders a search form for finding users to view their enrolments.
     *
     * @api GET /adminv2/shaps/courses/search
     * @since 18.1
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getUserEnrolmentSearch()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/courses/search.twig', [
            'pageCurrent' => $this->router->generate('shaps.getAll'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('shaps.getAll')),
        ])->setTitle('Schoolbox Help Course Enrolments - User Search'));
    }


    /**
     * Takes a user ID and renders a list of all courses that user is enrolled in on Help.
     * TODO placeholder method for when we actually implement this
     *
     * @api   GET /adminv2/shaps/courses/courses/{userId}
     * @since 18.1
     *
     * @param string $userID
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function getSearchCoursesForUser($userID): void
    {
        // Some sample users
        $zsmith = $this->userMapper->findById(151);
        $jbenson = $this->userMapper->findById(247);

        $mharris = $this->userMapper->findById(248);
        $mkelley = $this->userMapper->findById(249);

        $coursesForUser = [
            [
                "externalID" => $zsmith->getExternalID(),
                "courses" => [
                    [
                        "name" => "Random Course",
                        "id" => 1
                    ],
                    [
                        "name" => "Schoolbox Teacher Course",
                        "id" => 2
                    ]
                ]
            ],
            [
                "externalID" => $mharris->getExternalID(),
                "courses" => [
                    [
                        "name" => "Random Course",
                        "id" => 1
                    ]
                ]
            ],
            [
                "externalID" => $mkelley->getExternalID(),
                "courses" => [
                    [
                        "name" => "Random Course",
                        "id" => 1
                    ]
                ]
            ],
            [
                "externalID" => $jbenson->getExternalID(),
                "courses" => [
                    [
                        "name" => "Schoolbox Teacher Course",
                        "id" => 2
                    ]
                ]
            ],
        ];

        // If the user we searched for has sample data in the array above, render it out
        foreach ($coursesForUser as $user) {
            if ($user['externalID'] == $userID) {
                $userCourses = $user['courses'];
                $this->getResponse()->content->set($this->buildJsonOutput($userCourses));
            }
        }
    }


    /**
     * Post users to SHAPS
     *
     * @api   POST /adminv2/shaps/provision/
     * @since 181
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     */
    public function postProvision(): void
    {
        $userIds = $this->request->post->get('users');
        $courseIds = $this->request->post->get('courseIds');
        $provision = $this->request->post->get('provisioned');



        $response = $this->helpRequest->provisionUsers($userIds);

        if (!empty($courseIds)) {
            $response2 = $this->helpRequest->provisionUserParticipation($userIds, $courseIds);
        }

        // Build response
        $this->response->status->set($response['status']['code']);
        $this->response->content->set($this->buildJsonOutput($response));
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
            if (0 === \strpos($contentType, 'text/html')) {
                return 'Schoolbox Help API address appears misconfigured.';
            }
        }
        try {
            return $response->getBody()->getContents();
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }
}
