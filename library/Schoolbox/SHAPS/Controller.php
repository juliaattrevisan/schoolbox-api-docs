<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\SHAPS;

use Alaress\Web\Controller\Base;
use Schoolbox\IConfigLoader;
use Schoolbox\UserMapper;
use Schoolbox\Collection;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * A telephone to help.schoolbox.com.au where so can ask it things
     *
     * @var HelpRequest
     */
    private $helpRequest;


    /**
     * Controller constructor.
     *
     * @param IConfigLoader $configLoader
     * @param UserMapper    $userMapper
     */
    public function __construct(IConfigLoader $configLoader, UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        $this->helpRequest = new HelpRequest($configLoader, $this->userMapper);
    }


//    /**
//     * TODO: This isn't being used, currently: unsure if it was a placeholder for future implementation?
//     *
//     * @return string
//     * @throws \GuzzleHttp\Exception\GuzzleException
//     * @throws \InvalidArgumentException
//     * @throws \RuntimeException
//     */
//    public function getProvisionedUsersJSON() {
//        return json_encode($this->findProvisionedUsers());
//    }

    /*
     * Helper method to get all staff in your Schoolbox instance.
     *
     * @return Collection
     */
    private function getAllStaff() : Collection
    {
        // All the staff in our Schoolbox instance
        return $this->userMapper->findByColumn('g.group_type', \Schoolbox\Role::STAFF, [
            'order' => [
                'lastname' => 'asc',
                'firstname' => 'asc'
            ]
        ]);
    }


    /**
     * Renders a list of all staff in your Schoolbox instance, and whether or not they are provisioned on Help.
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getAll()
    {
        $provisionedUsers = $this->helpRequest->findProvisionedUsers();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/index.twig', [
                'allUsers' => $this->getAllStaff(),
                'provisionedUsers' => $provisionedUsers
            ])->setTitle('Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a list of staff in your Schoolbox instance that are also provisioned on Help.
     *
     * @api GET /shaps/provisioning/provisioned
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getProvisioned()
    {
        $provisionedUsers = $this->helpRequest->findProvisionedUsers();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/provisioned.twig', [
                    'allUsers' => $provisionedUsers,
                    'provisionedUsers' => $provisionedUsers
            ])->setTitle('Provisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a list of staff in your Schoolbox instance that are NOT provisioned on Help.
     *
     * @api GET /shaps/provisioning/unprovisioned
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getUnprovisioned()
    {
        $provisionedUsers = $this->helpRequest->findProvisionedUsers();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/unprovisioned.twig', [
                    'allUsers' => $this->getAllStaff()->diff($provisionedUsers)
            ])->setTitle('Unprovisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a selectize for searching users in your Schoolbox instance to see if they are provisioned.
     *
     * @api GET /shaps/provisioning/search
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getUserProvisioningSearch()
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioning/search.twig', [
            ])->setTitle('Search - Schoolbox Help Staff Provisioning')
        );
    }


    /**
     * Provides details about a user in response to an ajax request for that user's external ID.
     *
     * @api GET /shaps/provisioning/{userId}
     * @param string $userId
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function searchUsers(string $userId)
    {
        // Selectize gives us the user's external ID
        $user = $this->userMapper->findByExternalId($userId);

        /* Check if this user's id was in the provisionedUsers index - don't need to use in_array to test
         */
        $provisionedUsers = $this->helpRequest->findProvisionedUsers();
        $exists = $provisionedUsers->offsetGet($user->getId()) !== null;

        $userJSON = [
            'name' => $user->getFullName(),
            'provisioned' => $exists,
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($userJSON));
    }

    /**
     * Renders a list of all courses available for your school on Help, and the users enrolled in each.
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getUsersInCourses() {
        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/courses/index.twig', [
            'courses' => $this->helpRequest->findEnrolments(),
            'serverUrl' => $this->helpRequest->getServerUrl()
        ])->setTitle('Schoolbox Help Course Enrolments'));
    }

    /**
     * Renders a search form for finding users to view their enrolments.
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getUserEnrolmentSearch() {
        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/courses/search.twig', [
        ])->setTitle('Schoolbox Help Course Enrolments - User Search'));
    }

    /**
     * Takes a user ID and renders a list of all courses that user is enrolled in on Help.
     *
     * @param string $userID
     */
    public function searchCoursesForUser($userID) {
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
        foreach($coursesForUser as $user) {
            if ($user['externalID'] == $userID) {
                $userCourses = $user['courses'];
                $this->getResponse()->content->set($this->buildJsonOutput($userCourses));
            }
        }
    }


    /**
     * Post users to SHAPS
     *
     * @api POST /shaps/provision/
     * @level 181
     */
    public function postProvision(): void
    {
        $userIds     = $this->request->post->get('userId');
        $userPayload = [];
        if (is_iterable($userIds)) {
            foreach ($this->userMapper->findByColumn('id', $userIds) as $user) {
                /** @var User $user */
                if (!$user->getRole()->isStaff()) {
                    continue;
                }
                $userPayload[] = [
                    'id'        => $user->getId(),
                    'email'     => $user->getEmail(),
                    'title'     => $user->getTitle(),
                    'firstname' => $user->getFirstname(),
                    'lastname'  => $user->getLastname(),
                ];
            }
        }

        if (!empty($userPayload)) {
            // Send each user to Help

            $client = new GuzzleClient();

            $url = $this->getConfigLoader()->get('schoolbox_help_url');

            $schoolCode = $this->getConfigLoader()->get('site_school_code');

            $query = $this->generateQuery(Alaress_Date_Format::now());

            // CSRF defeating:
            // set HTTP_ORIGIN and HTTP_HOST to this school's URL
            $host     = $schoolCode . '.' . $this->getConfigLoader()->get('site_domain');
            $response = $client->post(
                "${url}/api/course-participation/${schoolCode}/users",
                [
                    'query'       => $query,
                    'form_params' => ['user' => $userPayload],
                    'headers'     => [
                        'Host'   => $host,
                        'Origin' => $host,
                    ],
                ]
            );

            // Do something with this?
//            return json_decode($response->getBody()->getContents(), true);
        }

        // Once done: send back to list
        $this->response->redirect->afterPost($this->router->generate('shaps.getAll'));
    }


    /**
     * Generate query params for this school to connect to the Help API.
     *
     * @todo consider moving this to a helper class
     *
     * @param Alaress_Date_Format $time
     * @param array               $extraParams
     * @return array
     */
    private function generateQuery(Alaress_Date_Format $time, array $extraParams = []): array
    {
        $schoolCode = $this->getConfigLoader()->get('site_school_code');
        $privateKey = $this->getConfigLoader()->get('schoolbox_help_access_token');

        $query = [
            'ts'    => $time->getUnixTimeStamp(),
            'token' => $this->generateToken($time, $schoolCode, $privateKey),
        ];
        if (\is_array($extraParams)) {
            $query = array_merge($query, $extraParams);
        }
        return $query;
    }


    /**
     * Generates a token for authenticating this school with the Help API.
     *
     * @todo once \Custom\Help\CourseParticipation\Handler::computeAccessToken is moved, replace this with new method
     *
     * @param Alaress_Date_Format $time
     * @param string              $schoolCode
     * @param string              $privateKey
     * @return string
     */
    private function generateToken(Alaress_Date_Format $time, string $schoolCode, string $privateKey): string
    {
        return sha1(sprintf(':%s-%s-%s:', $time, $schoolCode, $privateKey));
    }
}
