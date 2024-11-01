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
use Custom;
use Eportfolio_Authenticator;
use External_ClassInterface;
use External_UserInterface;
use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Geosnapshot\ValidStudentPhotosAvailable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Count\Helper as PastoralCountHelper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ProfessionalDevelopment\Mapper as ProfessionalDevelopmentMapper;
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
     * @param UserMapper $userMapper
     * @param RoleMapper $roleMapper
     * @param FolderMapper $folderMapper
     * @param Access $access
     * @param External_UserInterface $externalUser
     * @param External_ClassInterface $externalClass
     * @param ProfileButtonMapper $profileButtonMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param ProfessionalDevelopmentMapper $professionalDevelopmentMapper
     * @param PastoralTypeMapper $pastoralTypeMapper
     * @param PastoralSubTypeMapper $pastoralSubTypeMapper
     * @param PastoralDataSource $pastoralDataSource
     * @param TermMapper $termMapper
     * @param \Schoolbox\External\User $userRelationHydrator
     * @param Mapper $fieldMapper
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
        private ProfessionalDevelopmentMapper $professionalDevelopmentMapper,
        private PastoralTypeMapper $pastoralTypeMapper,
        private PastoralSubTypeMapper $pastoralSubTypeMapper,
        private PastoralDataSource $pastoralDataSource,
        private TermMapper $termMapper,
        private \Schoolbox\External\User $userRelationHydrator,
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
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
         */
        $showPastoral = $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ENABLED) && $this->getAuthorizer()->isPathAuthorizedForUser(
            'pastoral.getStudent',
            [
                'studentId' => $viewee->getId(),
                'student'   => $viewee,
            ],
            $viewer
        );

        $pastoralCountHelper = null;
        $pastoralCountPeriod = null;

        if ($showPastoral) {
            $pastoralCountHelper = (new PastoralCountHelper(
                $this->routeGenerator,
                $this->pastoralTypeMapper,
                $this->pastoralSubTypeMapper,
                $this->pastoralDataSource,
                $this->termMapper
            ));

            $pastoralCountPeriod = (int)$this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_FLAG_COUNT_PERIOD);
        }

        /* Full Payload Construction
         * $externalUser is the complete payload that we use to fulfil frontend
         *
         * Note this uses a callback to inject some data halfway through the compilation algorithm
         * to service this extra widgety thing, Pastoral Counts
         */
        $externalUser = (new \Schoolbox\Profile\Field\Factory($this->fieldMapper, $this->userMapper, $this->externalUser))
            ->produce($student, $this->getUser(), [
                /* Inject pastoral counts so it executes in proper place deeper in the Factory
                 * ...but only if Pastoral Counts were a renderable field, as per admin
                 */
                'pastoralCounts' => static function () use ($pastoralCountHelper, $pastoralCountPeriod, $viewee, $viewer) {
                    if ($pastoralCountHelper && $pastoralCountPeriod) {
                        return $pastoralCountHelper->countStudentEventsByTypes(
                            $viewer,
                            $viewee,
                            null,
                            null,
                            $pastoralCountHelper->getStartDate($pastoralCountPeriod)
                        );
                    }

                    return [];
                }
            ]);

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

                // Only show the parent contacts to the parent themselves, hide divorced parents from each other
                if (
                    $this->getUser()->getRole()->isStaff()
                    || ($this->getUser()->getRole()->isParent()
                        && ($contact['StudentContactID'] == $this->getUser()->getExternalId()
                            || $contact['StudentContactSpouseID'] == $this->getUser()->getExternalId()))
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

        $hideContacts = $student->getHideContacts() ? 1 : 0;

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

        $pd = null;
        if (strtolower($this->getConfigLoader()->get('site_school_code')) === 'help') {
            /* Load Professional Development details if they exist
             */
            try {
                $pd = $this->professionalDevelopmentMapper->findOneByColumn('user_id', $student->getId());
            } catch (MapperException $e) {
                // noop
            }
        }

        $buttons = ProfileButtonMapper::getInstance()->findButtonsForUser($student, $this->getUser());
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

        /* For a student's classes tiles to be visible, they must pass the sis
         * checks determined by $showDetails and their timetable must be visible
         * via the Hide Timetable checkbox in their Modify User screen
         */
        $showClasses = $showDetails && !$student->getHideTimetable();

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
        $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
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
                'showPastoral' => $showPastoral,
                /* This now incudes system buttons - there's no longer really a distinction between system and
                 * custom buttons, as far as the end user is concerned
                 */
                'buttons' => $buttons,

                /* Old implementation
                 */
                'session' => $_SESSION,
                /* This is mostly superseded by user.showTimetable in twig
                 */
                'showClasses' => $showClasses,
                'eportfolioAuth'           => $this->eportfolioAuthenticator->canView(),
                'hideContacts'             => $hideContacts,
                'now'                      => Alaress_Date_Format::now(),
                'contacts'                 => $contacts,
                'subjects'                 => $subjects,
                'groups'                   => $groups,
                'isParentOfStudent'        => $isParentOfStudent,
                'isPrimaryCarer'           => $isPrimaryCarer,
                'allTeacherIds'            => implode(',', $allTeacherIds),
                'pdStates'                 => $this->professionalDevelopmentMapper->getStates(),
                'pd'                       => $pd,
                'showEmail'                => $showEmails,
                'travelLogs'               => $travelLogs,
                'learningMomentCanComment' => $learningMomentCanComment,
                'isGeosnapshotAvailable'   => $isGeosnapshotAvailable,
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
     * @throws \DomainException
     * @throws \UnexpectedValueException
     */
    private function renderStaff(User $staff): void
    {
        /* Full Payload Construction
         * $externalUser is the complete payload that we use to fulfil frontend
         *
         * Note this uses a callback to inject some data halfway through the compilation algorithm
         * to service this extra widgety thing, Pastoral Counts
         */
        $externalUser = (new \Schoolbox\Profile\Field\Factory($this->fieldMapper, $this->userMapper, $this->externalUser))
            ->produce($staff, $this->getUser());

        try {
            $customButtons = $this->profileButtonMapper->findButtonsForUser($staff, $this->getUser());
        } catch (MapperException $e) {
            $customButtons = [];
        }

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

                /* Old implementation
                 */
                'session'        => $_SESSION,
                'user'           => $staff,
                'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
                'customButtons'  => $customButtons,
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
        $externalUser = (new \Schoolbox\Profile\Field\Factory($this->fieldMapper, $this->userMapper, $this->externalUser))
            ->produce($parent, $this->getUser());

        try {
            $customButtons = ProfileButtonMapper::getInstance()->findButtonsForUser($parent, $this->getUser());
        } catch (MapperException $e) {
            $customButtons = [];
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

                /* Old implementation
                 */
                'session'        => $_SESSION,
                'user'           => $parent,
                'students'       => $parent->getChildren(),
                'customButtons'  => $customButtons,
                'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
                'now'            => Alaress_Date_Format::now(),
                'showDetails'    => (new ValidDetailsVisible($this->externalUser, $this->getConfigLoader()))->isSatisfiedBy($parent, $this->getUser()),
                'sharedCampuses' => $this->getUser()->getCampusCollection()->intersect($parent->getCampusCollection()),
            ]
        );
    }
}
