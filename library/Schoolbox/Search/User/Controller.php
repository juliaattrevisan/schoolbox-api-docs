<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\User;

use Alaress\Color\ColorFactory;
use Alaress\Web\Controller\Base as BaseController;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Custom;
use Eportfolio_Authenticator;
use External_ClassInterface;
use External_UserInterface;
use Calendar_Api_Factory;
use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Geosnapshot\ValidPhotosAvailable;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Moment\Evidence\Moderation\ModerationRequired;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Count\Helper as PastoralCountHelper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\ValidDetailsVisible;
use Schoolbox\Profile\ValidEmailVisible;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Code\ClassCodeMatchFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\SortUsersByName;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Timetable_ColourManager;
use Timetable_ColourMapper;
use Twig\Environment;
use Twig\Error\Error;

/**
 * The controller for the mock version of the search API.
 */
class Controller extends BaseController
{
    /**
     * Eportfolio_Authenticator
     */
    private Eportfolio_Authenticator $eportfolioAuthenticator;


    /**
     * Constructor
     * @param UserMapper                                 $userMapper
     * @param RoleMapper                                 $roleMapper
     * @param FolderMapper                               $folderMapper
     * @param Access                                     $access
     * @param External_UserInterface                     $externalUser
     * @param External_ClassInterface                    $externalClass
     * @param ProfileButtonMapper                        $profileButtonMapper
     * @param Timetable_ColourMapper                     $timetableColourMapper
     * @param PastoralTypeMapper                         $pastoralTypeMapper
     * @param PastoralSubTypeMapper                      $pastoralSubTypeMapper
     * @param PastoralDataSource                         $pastoralDataSource
     * @param TermMapper                                 $termMapper
     * @param \Schoolbox\External\User                   $userRelationHydrator
     * @param Mapper                                     $fieldMapper
     * @param \Schoolbox\Admin\Profile\Editor\Tab\Mapper $tabMapper
     * @param Calendar_Api_Factory                         $calendarApiFactory
     */
    public function __construct(
        private UserMapper $userMapper,
        private RoleMapper $roleMapper,
        private FolderMapper $folderMapper,
        private Access $access,
        private External_UserInterface $externalUser,
        private External_ClassInterface $externalClass,
        private ProfileButtonMapper $profileButtonMapper,
        private Timetable_ColourMapper $timetableColourMapper,
        private PastoralTypeMapper $pastoralTypeMapper,
        private PastoralSubTypeMapper $pastoralSubTypeMapper,
        private PastoralDataSource $pastoralDataSource,
        private TermMapper $termMapper,
        private \Schoolbox\External\User $userRelationHydrator,
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\Admin\Profile\Editor\Tab\Mapper $tabMapper,
        private Calendar_Api_Factory $calendarApiFactory,
        private RosettaStone $rosettaStone,
    ) {
    }


    /**
     * Pick a profile screen for which to provide a payload
     *
     * @param User $user
     * @return void
     * @throws MapperException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function getProfile(User $user): void
    {
        $this->eportfolioAuthenticator = new Eportfolio_Authenticator($this->getUser(), $user);

        if ($user->getRole()->isStaff()) {
            $this->renderStaff($user);
        } elseif ($user->getRole()->isStudent()) {
            $this->userRelationHydrator->loadParents([$user]);
            $this->renderStudent($user);
        } elseif ($user->getRole()->isParent()) {
            $this->userRelationHydrator->loadChildren([$user]);
            $this->renderParent($user);
        }
    }


    /**
     * Provide a payload for student profile screen
     *
     * @param User $student
     * @return void
     * @throws MapperException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    private function renderStudent(User $student): void
    {
        $viewee = $student;
        $viewer = $this->getUser();

        /* Pastoral Count injection
         *
         * Find out if Pastoral Care is enabled and that you have access to a student's pastoral care records
         * If you do, remember its url
         */
        try {
            $pastoralUrl = $this->getRouteIfAuthorized(
                'pastoral.getStudent',
                [
                    'studentId' => $viewee->getId(),
                    'student'   => $viewee,
                ],
                $viewer
            );
        } catch (RouteNotFound) {
            $pastoralUrl = false;
        }

        $showPastoral = false;

        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ENABLED) && $pastoralUrl) {
            $showPastoral = true;
        }

        $pastoralCountHelper = null;

        if ($showPastoral) {
            $pastoralCountHelper = (new PastoralCountHelper(
                $this->routeGenerator,
                $this->pastoralTypeMapper,
                $this->pastoralSubTypeMapper,
                $this->pastoralDataSource,
                $this->termMapper
            ));
        }

        /* Full Payload Construction
         * $externalUser is the complete payload that we use to fulfil frontend
         *
         * Note this uses a callback to inject some data halfway through the compilation algorithm
         * to service this extra widgety thing, Pastoral Counts
         */
        $externalUser = (new \Schoolbox\Profile\Field\Factory(
            $this->fieldMapper,
            $this->userMapper,
            $this->externalUser,
            $this->calendarApiFactory,
        ))->produce($student, $this->getUser(), [
                /* Inject pastoral counts so it executes in proper place deeper in the Factory
                 * ...but only if Pastoral Counts were a renderable field, as per admin
                 */
                'pastoralCounts' => static function () use ($pastoralCountHelper, $viewee, $viewer) {
                    if ($pastoralCountHelper) {
                        return $pastoralCountHelper->countStudentEventsByTypes(
                            $viewer,
                            $viewee,
                        );
                    }

                    return [];
                }
            ]);

        /* Profile Buttons
         */
        $buttons = $this->profileButtonMapper->findButtonsForUser($student, $this->getUser());

        /* Profile Tabs
         */
        $tabs = $this->tabMapper->findRenderable($student, $this->getUser());

        /* Whether Learning Moment Moderation was required for this student; this forces their
         * learning moment posts to be moderated by a staff before being pushed into the world
         */
        $globalConfig = (bool)$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED);
        $moderationRequired = (new ModerationRequired())->isSatisfiedBy($globalConfig, $this->getUser()->getYear());

        /* Old implementation below
         */

        // Wrangle default colors
        $colors              = $this->timetableColourMapper->findAll()->reindex();
        $colorFactory        = new ColorFactory();
        $subjectColorManager = new Timetable_ColourManager($colors);
        $groupColorManager   = new Timetable_ColourManager($colors);


        // Load the list of subjects, and their teacher.
        // Then group by subject type?
        $subjects      = [];
        $splitSubjects = $this->getConfigLoader()->get('mysubjects_split');
        $teacherRoles  = $this->roleMapper->findByColumn('group_type', Role::STAFF)->getPropertyValues('id');
        $codeMatcher   = (new ClassCodeMatchFactory())->build($this->getConfigLoader()->get('external_type'));
        $subjectList   = $this->externalUser->getSubjectList($student);
        foreach ($subjectList as $subject) {
            /* Decide whether the currently logged in user can see a link
             * to this subject's folder; link availability is dependent
             * on the folder existing and the current user having >= READ
             * permission on that folder
             *
             * Mindfully deconflict any array keys that might have come out of
             * the external connector's array of data
             */
            $subject['linkedToFolder'] = false;

            $group = $splitSubjects && $subject['classType'] ? $subject['classType'] : 'Classes';
            // Load a bunch of category and teacher info too
            $folder = $this->folderMapper->findForCampusAndClass($subject['campusCode'] ?? '', $subject['code']);
            if ($folder) {
                $subject['folder'] = $folder;
                $subject['perms']  = $this->access->getAccessLevel($this->getUser(), UserAccess::SERVICE_HOMEPAGE, $folder->getId());

                /* At this point we know the folder exists
                 * if the user has >= READ access, then they can view the link to the folder
                 * With more elbow grease this becomes unit testable
                 */

                $subject['linkedToFolder'] = $subject['perms'] >= UserAccess::ACCESS_READ;

                // If config flag enabled, only fetch teacher(s) for folder's codes which map to an enrolled subject
                $taughtFolderCodes = $folder->getCodeString();
                if ($this->getConfigLoader()->get('student_teacher_class_visibility')) {
                    $folderCodesRetained = [];
                    foreach (explode(',', $taughtFolderCodes) as $folderCode) {
                        foreach ($subjectList as $enrolledSubject) {
                            if ($codeMatcher->isMatch($folderCode, $enrolledSubject['campusCode'], $enrolledSubject['code'])) {
                                $folderCodesRetained[] = $folderCode;
                                break;
                            }
                        }
                    }
                    $taughtFolderCodes = implode(',', $folderCodesRetained);
                }
            } elseif ($this->getConfigLoader()->get('show_all_subjects')) {
                $subject['folder'] = $this->folderMapper->createModel()
                    ->setName($subject['name'])
                    ->setCodes(FolderCode::createCollection())
                    ->addCode((new FolderCode())->setCode($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code'])));
                $subject['perms']  = UserAccess::ACCESS_DENY;
                $taughtFolderCodes = $subject['folder']->getCodeString();
            } else {
                continue;
            }

            // Apply a color if there is none
            if ($colors->count() > 0 && !$subject['folder']->getColour()) {
                $subject['folder']->setColour(
                    $colorFactory->build(
                        $subjectColorManager->getColour($subject['folder']->getCodeString())
                    )
                );
            }

            $teacherCodes = $this->externalClass->getTeachers($taughtFolderCodes);
            if (count($teacherCodes) > 0) {
                $subject['teachers'] = $this->userMapper->findByColumnList(
                    [
                        'synergy_id' => $teacherCodes,
                        'group_id'   => $teacherRoles,
                    ]
                )->sortByCallback((new SortUsersByName())->getCallable());
            } else {
                $subject['teachers'] = [];
            }

            $subjects[$group][] = $subject;
        }

        /* Forcibly sort the subjects alphabetically and as au natural as possible
         * as per the php docs
         */
        ksort($subjects, SORT_NATURAL);

        // Load the list of groups, and their owner and folder.
        $groupFolders = $this->folderMapper->findForUser($student, false, UserAccess::ACCESS_READ, false, true);
        $groups       = [];
        foreach ($groupFolders as $groupFolder) {
            $group           = [];
            $group['id']     = $groupFolder->getId();
            $group['name']   = $groupFolder->getName();
            $group['perms']  = $this->access->getFolderAccess($this->getUser(), $groupFolder->getId());
            $group['folder'] = $groupFolder;


            /* Decide whether the currently logged in user can see a link
             * to this group's folder; link availability is dependent
             * the current user having >= READ permission on that folder
             *
             * Mindfully deconflict any array keys that might have come out of
             * the external connector's array of data
             */
            $group['linkedToFolder'] = $group['perms'] >= UserAccess::ACCESS_READ;

            // Apply a color if there is none
            if ($colors->count() > 0 && !$groupFolder->getColour()) {
                $group['folder']->setColour(
                    $colorFactory->build(
                        $groupColorManager->getColour($groupFolder->getId())
                    )
                );
            }

            $group['owners'] = $this->userMapper->findByAccess(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_FULL,
                $groupFolder->getId(),
            )->sortByCallback((new SortUsersByName())->getCallable());

            $groups[] = $group;
        }

        // Load contact's user IDs
        $contacts = [];
        $contactList = [];
        if ($student->getExternalId()) {
            $contactList = $this->externalUser->getStudentContactList([$student->getExternalId()]);
            foreach ($contactList as $contact) {
                $main = null;
                try {
                    $main = $this->userMapper->findByExternalId($contact['StudentContactID'], Role::PARENT);
                } catch (MapperException $e) {
                    // noop
                }
                $spouse = null;
                try {
                    $spouse = $this->userMapper->findByExternalId($contact['StudentContactSpouseID'], Role::PARENT);
                } catch (MapperException $e) {
                    // noop
                }

                /* This is maybe a controversial change
                 */
                if (
                    /* Staff members can see contacts
                     */
                    $this->getUser()->getRole()->isStaff() ||
                    /* The student viewing their own profile can see the contacts...
                     * ...maybe. Why was this prohibited originally?
                     */
                    ($this->getUser()->getExternalId() === $student->getExternalId()) ||
                    /* Parents can see their own details, but not the details of any other speece
                     * A parent viewing this profile page might be the main person or the spouse
                     * so you need to check both for a matching externalId
                     */
                    ($this->getUser()->getRole()->isParent() && $contact['StudentContactID'] === $this->getUser()->getExternalId()) ||
                    ($this->getUser()->getRole()->isParent() && $contact['StudentContactSpouseID'] === $this->getUser()->getExternalId())
                ) {
                    $contact['main']   = $main;
                    $contact['spouse'] = $spouse;
                    $contacts[]        = $contact;
                }
            }
        }

        // Determine whether current user is student's parent, and if not if they
        // are related in some other way.

        $isParentOfStudent = $this->getUser()->isParentOf($student);
        $isPrimaryCarer    = false;
        if ($isParentOfStudent && !empty($contactList)) {
            // Only the primary parent will see emergency contact information
            if (
                $contactList[0]['StudentContactID'] == $this->getUser()->getExternalId()
                || $contactList[0]['StudentContactSpouseID'] == $this->getUser()->getExternalId()
            ) {
                $isPrimaryCarer = true;
            }
        }



        $protectStudentContacts = $this->getConfigLoader()->get('protect_student_contacts');

        switch ($protectStudentContacts) {
            case Access::VISIBILITY_EVERYONE: // Everyone
                break;
            case Access::VISIBILITY_STAFF: // Staff Only
                if (!$this->getUser()->getRole()->isStaff()) {
                    $hideContacts = true;
                }
                break;
            case Access::VISIBILITY_STAFF_PARENT: // Staff & Parents Only
                if (!$this->getUser()->getRole()->isStaff() && !$this->getUser()->getRole()->isParent()) {
                    $hideContacts = true;
                }
                break;
        }

        $allTeacherIds = [];

        foreach ($subjects as $subject) {
            foreach ($subject as $class) {
                /** @var User $teacher */
                foreach ($class["teachers"] as $teacher) {
                    $allTeacherIds[] = $teacher->getId();
                }
            }
        }

        $teacherEmails = array_filter([
            $externalUser['formTeacher'] ?? null,
            $externalUser['tutorTeacher'] ?? null,
            $externalUser['hohTeacher'] ?? null,
            $externalUser['coordTeacher'] ?? null,
            $externalUser['studentCoach'] ?? null,
        ]);

        /** @var \Schoolbox\User $object */
        foreach ($teacherEmails as $object) {
            $allTeacherIds[] = $object->getId();
        }
        $allTeacherIds = array_unique(array_filter($allTeacherIds));

        $showDetails   = (new ValidDetailsVisible($this->externalUser, $this->getConfigLoader()))->isSatisfiedBy($student, $this->getUser());
        $showEmails    = (new ValidEmailVisible($this->externalUser, $this->getConfigLoader()))->isSatisfiedBy($student, $this->getUser());

        // Get the Verso school's Travel Log data
        $travelLogRenderer = new Custom\Vist\TravelLog\Renderer(
            $this->getConfigLoader()->get('site_school_code'),
            $this->getConfigLoader()->get('verso_client_id'),
            $this->getConfigLoader()->get('verso_client_secret'),
            $this->getConfigLoader()->get('travelLog_api_caching')
        );

        // Get the data from Verso API.
        /** @var Environment $twig */
        $twig       = $this->getDi()->get('templateEngine');
        $travelLogs = $travelLogRenderer->render($twig, $student->getExternalId(), true);

        /* Decide whether the current user can comment on this student's learning moments
         *
         * Staff and student can always comment
         * Parents can comment if the config allows
         * Everyone else cannot
         */
        $learningMomentCanComment = ($this->getUser()->getRole()->isStaff()
            || $this->getUser()->getRole()->isStudent()
            || ($this->getUser()->getRole()->isParent() && $this->getConfigLoader()->get('evidence_parent_comment')));

        /* Decide whether View Photos (Geosnapshots) is available for this student
         * If you're allowed to make this request...
         */
        $isGeosnapshotAvailable = (new ValidPhotosAvailable())
            ->isSatisifiedBy(
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                $this->getUser(),
                $student
            );

        /* Decide whether the current user can email staff members
         *
         * This is controlled by the config report_staff_access "Allow Parents Access to Staff Details"
         * and applies when you're a parent
         */
        $canEmailStaff = true;

        if (!$this->getConfigLoader()->get('report_staff_access') && $this->getUser()->getRole()->isParent()) {
            $canEmailStaff = false;
        }

        // Does the current user share a campus with the student?
        // This may matter for multi-school deployments.
        // Eventually: this will likely be made a config setting with wider
        // scope (e.g. search results, grades).
        $sharedCampuses = $this->getUser()->getCampusCollection()->intersect($student->getCampusCollection());


        /* Further restrict access to profile tabs more than just by availability and visibility (which is what $tabs is right now)
         */
        $hasEmergencyContacts = count($externalUser['data']['roleTypes']['tabs']['emergencyContacts']) > 0;

        /* (For all role types) If any of these fail, the tab should never exist */
        $tabConstraintsBase = [
            /* unless the tab is known in this list, it should not be shown */
            '_default'     => false,
            'activity'     => ($this->getConfigLoader()->get('learning_moments_enabled') && $student->getLearningMomentsEnabled())
                || $this->getConfigLoader()->get('badges_enabled')
                || $this->getConfigLoader()->get('grades_enabled'),
            'goals'        => $this->getConfigLoader()->get('badges_enabled'),
            'achievements' => $this->getConfigLoader()->get('badges_enabled'),
            'classes'      => $student->isShowTimetable(),
            'groups'       => true,
            /**
             * TODO: Check this for if it actually should differ between role types
             * (from the twig) contacts: users must have showContacts truthy and have at least one contact
             * or the viewer is a primary carer and the viewee has at least one
             * emergency contact. This is really particular.
             *
             * See tabConstraintsRoleType
             */
            'contacts'     => true,
        ];

        /* (For specific role types) If any of these fail, the tab should never exist */
        $tabConstraintsRoleType = [
            'student' => [
                /* only a student can view their own profile tabs, this is checked by $tabs already so fall back to true */
                '_default' => true,
                'contacts' => (($student->isShowContacts() || $isPrimaryCarer) && (count($contacts) > 0 || $hasEmergencyContacts)),
            ],
            'parent'  => [
                /* only parents of a student can view the contacts tab, this is checked by $tabs already so fall back to true */
                '_default' => true,
                'contacts' => (($student->isShowContacts() || $isPrimaryCarer) && (count($contacts) > 0 || $hasEmergencyContacts)),
            ],
            'staff'   => [
                /* staff can view a student's profile tabs if their role has viewStudentProfile enabled */
                '_default' => $this->getUser()->getRole()->getViewStudentProfile(),
                'contacts' => $this->getUser()->getRole()->getViewStudentProfile()
                    && (($student->isShowContacts() && count($contacts) > 0) || ($isPrimaryCarer && $hasEmergencyContacts)),
            ],
        ];

        /* deactivate any tab if it doesn't meet the additional constraints above */
        $tabs = $tabs->filter(function ($tab) use ($tabConstraintsBase, $tabConstraintsRoleType) {
            $roleTypeConstraints = $tabConstraintsRoleType[$this->getUser()->getRole()->getType()];

            if (($roleTypeConstraints[$tab->getTab()] ?? $roleTypeConstraints['_default']) === false) {
                return false;
            }

            if (($tabConstraintsBase[$tab->getTab()] ?? $tabConstraintsBase['_default']) === false) {
                return false;
            }

            return true;
        });

        $this->response = $this->responseFromTemplate(
            'user/student.twig',
            [
                /* Profile Editor
                 */
                /* This is an augmented object consisting of schoolbox user fields
                 * and external sis fields from the External Interface (hybrid theory)
                 */
                'externalUser' => $externalUser,
                /* Profile screens can have their own roletype-specific name format
                 */
                'nameFormat' => $this->getConfigLoader()->get('name_format_student'),
                /* The viewee's User instance - sadly, very easy to confuse with USER global, which means
                 * the viewer, so be careful with that
                 */
                'user' => $student,
                /* Takes into account whether the viewer has the View Student Profile role permission
                 * in addition to some other standard checks for Guest-ness. We need to keep that.
                 * Most user details are in a block that is toggled by this var
                 */
                'showDetails' => $showDetails,
                /* Decides if pastoral buttons and stuff actually exist beyond our Availability and
                 * Visibility settings (ie. does your user have permissions etc to this place)
                 *
                 * We needed to keep this
                 */
                'pastoralUrl' => $pastoralUrl,
                /* This now includes system buttons - there's no longer really a distinction between system and
                 * custom buttons, as far as the end user is concerned
                 */
                'buttons' => $buttons,
                /* A sequenced list of tabs that the user can see
                 */
                'possibleTabs' => $tabs,
                /* Retain this old check
                 */
                'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
                /* This is used for the calendar url, a dynamic set of args
                 */
                'now' => Alaress_Date_Format::now(),
                /* Used for deciding whether you can see emails or not via its own idiosyncratic
                 * heuristic beyond Availability/Visibility concepts
                 */
                'showEmail' => $showEmails,
                /* Somehow this is important
                 */
                'isPrimaryCarer' => $isPrimaryCarer,
                /* A set of contacts (if any) for this student
                 */
                'contacts' => $contacts,
                /* Decides if the Geosnapshot button could be accessed
                 */
                'isGeosnapshotAvailable' => $isGeosnapshotAvailable,
                /* Used for emailing staff (Email All Teachers)
                 */
                'allTeacherIds' => implode(',', $allTeacherIds),
                /* The current user session - sadly a superglobal for portability reasons
                 */
                'session' => $_SESSION,
                /* Links to be displayed in the 3-dot menu.
                 */
                'linksInfo' => $this->getProfileThreeDotMenuLinksInfo($viewer, $student),
                /* Learning Moment Moderation requirement for this student
                 */
                'moderationRequired' => $moderationRequired,

                /* Old implementation
                 */
                'subjects'                 => $subjects,
                'groups'                   => $groups,
                'travelLogs'               => $travelLogs,
                'learningMomentCanComment' => $learningMomentCanComment,
                'canEmailStaff'            => $canEmailStaff,
                'sharedCampuses'           => $sharedCampuses,
            ]
        );
    }


    /**
     * Provide a payload for staff profile screen
     *
     * @param User $staff
     * @return void
     * @throws MapperException
     * @throws Error
     */
    private function renderStaff(User $staff): void
    {
        /* Full Payload Construction
         * $externalUser is the complete payload that we use to fulfil frontend
         *
         * Note this uses a callback to inject some data halfway through the compilation algorithm
         * to service this extra widgety thing, Pastoral Counts
         */
        $externalUser = (new \Schoolbox\Profile\Field\Factory(
            $this->fieldMapper,
            $this->userMapper,
            $this->externalUser,
            $this->calendarApiFactory,
        ))->produce($staff, $this->getUser());

        try {
            $buttons = $this->profileButtonMapper->findButtonsForUser($staff, $this->getUser());
        } catch (MapperException $e) {
            $buttons = [];
        }

        /* Decide whether View Photos (Geosnapshots) is available for this student
         * If you're allowed to make this request...
         */
        $isGeosnapshotAvailable = (new ValidPhotosAvailable())
            ->isSatisifiedBy(
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                $this->getUser(),
                $staff
            );

        $this->response = $this->responseFromTemplate(
            'user/staff.twig',
            [
                /* Profile Editor
                 */
                /* This is an augmented object consisting of schoolbox user fields
                 * and external sis fields from the External Interface (hybrid theory)
                 */
                'externalUser' => $externalUser,
                /* Profile screens can have their own roletype-specific name format
                 */
                'nameFormat' => $this->getConfigLoader()->get('name_format_staff'),
                /* This now includes system buttons - there's no longer really a distinction between system and
                 * custom buttons, as far as the end user is concerned
                 */
                'buttons'  => $buttons,
                /* Decides if the Geosnapshot button could be accessed
                 */
                'isGeosnapshotAvailable' => $isGeosnapshotAvailable,
                /* Links to be displayed in the 3-dot menu.
                 */
                'linksInfo'      => $this->getProfileThreeDotMenuLinksInfo($this->getUser(), $staff),

                /* Old implementation
                 */
                'session'        => $_SESSION,
                'user'           => $staff,
                'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
                'now'            => Alaress_Date_Format::now(),
                'showDetails'    => (new ValidDetailsVisible($this->externalUser, $this->getConfigLoader()))->isSatisfiedBy($staff, $this->getUser()),
            ],
            'User'
        );
    }


    /**
     * Provide a payload for parent profile screen
     *
     * @param User $parent
     * @return void
     * @throws MapperException
     * @throws \DomainException
     * @throws \UnexpectedValueException
     */
    private function renderParent(User $parent): void
    {
        /* Full Payload Construction
         * $externalUser is the complete payload that we use to fulfil frontend
         *
         * Note this uses a callback to inject some data halfway through the compilation algorithm
         * to service this extra widgety thing, Pastoral Counts
         */
        $externalUser = (new \Schoolbox\Profile\Field\Factory(
            $this->fieldMapper,
            $this->userMapper,
            $this->externalUser,
            $this->calendarApiFactory,
        ))->produce($parent, $this->getUser());

        try {
            $buttons = $this->profileButtonMapper->findButtonsForUser($parent, $this->getUser());
        } catch (MapperException $e) {
            $buttons = [];
        }

        // As with students in SA-16859:
        // set the shared campuses between the subject of the profile and the
        // viewing user, to determine what (if any) of the profile can be shown.
        $this->response = $this->responseFromTemplate(
            'user/parent.twig',
            [
                /* Profile Editor
                 */
                /* This is an augmented object consisting of schoolbox user fields
                 * and external sis fields from the External Interface (hybrid theory)
                 */
                'externalUser' => $externalUser,
                /* Profile screens can have their own roletype-specific name format
                 */
                'nameFormat' => $this->getConfigLoader()->get('name_format_parent'),
                /* This now includes system buttons - there's no longer really a distinction between system and
                 * custom buttons, as far as the end user is concerned
                 */
                'buttons'  => $buttons,
                /* Links to be displayed in the 3-dot menu.
                 */
                'linksInfo'      => $this->getProfileThreeDotMenuLinksInfo($this->getUser(), $parent),

                /* Old implementation
                 */
                'session'        => $_SESSION,
                'user'           => $parent,
                'students'       => $parent->getChildren(),
                'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
                'now'            => Alaress_Date_Format::now(),
                'showDetails'    => (new ValidDetailsVisible($this->externalUser, $this->getConfigLoader()))->isSatisfiedBy($parent, $this->getUser()),
                'sharedCampuses' => $this->getUser()->getCampusCollection()->intersect($parent->getCampusCollection()),
            ]
        );
    }

    /**
     * Return an array containing information about the links to be displayed in the 3-dot menu at the top-right of the
     * profile page.  Links not accessible by the user are filtered out in the returned array.
     */
    private function getProfileThreeDotMenuLinksInfo(User $viewer, User $viewee): array
    {
        // Get URLs for routes, while handling RouteNotFound exceptions.
        $routeParamsByName = [
            'adminUser.getModify' => ['userId' => $viewee->getId(), 'user' => $viewee],
            'user.impersonate' => ['userId' => $viewee->getId(), 'user' => $viewee],
            'pastoral.getStudentRecordInsertForm' => ['studentId' => $viewee->getId(), 'student' => $viewee],
        ];
        $urlByRouteName = [];
        foreach ($routeParamsByName as $routeName => $routeParams) {
            try {
                $url = $this->getRouteIfAuthorized($routeName, $routeParams);
            } catch (RouteNotFound) {
                $url = false;
            }
            $urlByRouteName[$routeName] = $url;
        }

        return array_filter(
            [
                [
                    'title' => 'Modify User',
                    'url' => $urlByRouteName['adminUser.getModify'],
                    'iconClass' => 'icon-modify',
                    'disabled' => false,
                    'mouseoverText' => 'Modify User',
                    'dataTest' => 'user-profile-modify'
                ],
                [
                    'title' => 'Impersonate',
                    'url' => ($viewer->getId() === $viewee->getId()) ? false : $urlByRouteName['user.impersonate'],
                    'iconClass' => 'icon-staff-students',
                    'disabled' => $viewee->isDeleted(),
                    'mouseoverText' => $viewee->isDeleted() ? 'Account Disabled' : 'Login as user',
                    'dataTest' => $viewee->isDeleted() ? '' : 'admin-user-modify-impersonate'
                ],
                [
                    'title' => 'Create ' . $this->rosettaStone->translate('pastoral', false, true) . ' Record',
                    'url' => (
                        // Only show the 'Create pastoral record' link if the viewee is a student.
                        // Note cannot use permission check to decide whether to show the link, because route
                        // permissions allow creation of pastoral records on non-student users.
                        $viewee->getRole()->isStudent()
                        ? $urlByRouteName['pastoral.getStudentRecordInsertForm']
                        : false
                    ),
                    'iconClass' => 'icon-pastoral',
                    'disabled' => false,
                    'mouseoverText' => 'Create Pastoral Record',
                    'dataTest' => ''
                ]
            ],
            fn ($item) => $item['url'] !== false
        );
    }
}
