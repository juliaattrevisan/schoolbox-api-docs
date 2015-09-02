<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_UpcomingWorkController extends Component_Dashboard_ControllerAbstract
{
    /**
     * Stores the list of upcoming work returned and parsed from the database
     * @var array
     */
    private $upcomingWork = array();

    /**
     * Used to indicate whether or not the 'View all Due Work' button should be displayed (if actionable is true)
     * @var bool
     */
    private $restrictedMode;

    /**
     * The maximum number of items to show
     * @var int
     */
    private $limit = 0;

    /**
     * The ID of the user being displayed
     * @var int
     */
    private $userID;

    /**
     * Determines whether or not the action buttons should be displayed on each list item
     * @var bool
     */
    private $actionable;

    /**
     * Are links to quizzes shown? Currently always equal to $this->actionable
     *
     * @var bool
     */
    private $showQuizLink;

    /**
     * Are links to assessments shown? Currently equal to $actionable, unless
     * user is a parent and restrict_student_submissions is off - then it's true
     *
     * @var bool
     */
    private $showAssessLink;

    /**
     * Business logic to collate data ready for output
     * @return bool
     */
    public function execute()
    {
        $this->userID = $this->getConfig('user') ? $this->getConfig('user') : $_SESSION['id'];
        // if actionable is absent, then this is the dashboard component and restricted should be true.
        // It should still be actionable though.
        if ($this->hasConfig('actionable')) {
            $this->actionable = $this->getConfig('actionable') ? true : false;
        } else {
            $this->actionable = true;
            $this->restrictedMode = true;
        }

        $this->limit = is_numeric($this->getConfig('size')) ? $this->getConfig('size') : 0;

        if ($this->getConfig('allow_parents')) {
            $this->restrictedMode = false;
        }

        // This component has access restrictions
        // when displayed to a student, actions are available, otherwise not
        $groupHandler = new Group();

        if (!$groupHandler->isStudent()) {
            if ($this->restrictedMode) {
                return false;
            }
            $this->actionable = false;
        }

        $submissionHandler = new Submission();

        $upcoming = $submissionHandler->getAllUpcomingWorkAndAssessments($this->userID);

        $now = time();
        $j = 0;

        if (!count($upcoming)) {
            return false;
        }

        // Show assess or quiz links? Depends on $actionable
        $this->showAssessLink = $this->actionable;
        $this->showQuizLink   = $this->actionable;

        // if this is a parent viewing their child's work
        if ($groupHandler->isParentOfChild($this->userID)) {
            // Check if system config restrict_student_submissions is on.
            // Parents can view assess links if it's off.
            $this->restrictedMode = Config::get('restrict_parent_grades');
            $this->showAssessLink = !Config::get('restrict_student_submissions');
        }

        $isStaff = $groupHandler->isStaff();
        foreach ($upcoming as $item) {
            unset($output);
            // find out if we're actually showing this item
            // Skip if I'm not a staff member and this isn't yet open
            if (!$isStaff && $item['publish_date'] > $now) {
                continue;
            }
            if ($item['kind'] == 0) {
                // duework
                if ($item['submittable']) {
                    if ($item['multi_submit']) {
                        if (!$item['item_count'] && $item['late_date'] < $now) {
                            // if unsubmitted, hide if LATE date passed
                            $output['HIDEME'] = 'hideme multi unsubmitted late-passed';
                            $output['upcoming_work_actionable_block'] = 'hideme multi unsubmitted late-passed';
                        } elseif ($item['submit_count'] && $item['date'] < $now) {
                            // if submitted, hide if DUE date passed
                            $output['HIDEME'] = 'hideme multi submitted due-passed';
                            $output['upcoming_work_actionable_block'] = 'hideme multi submitted due-passed';
                        }
                    } else {
                        // single submit: hide if completed or DUE date in past
                        if ($item['date'] < $now || $item['submit_count']) {
                            $output['HIDEME'] = 'hideme single due-passed-or-submitted';
                            $output['upcoming_work_actionable_block'] = 'hideme single due-passed-or-submitted';
                        }
                    }
                } else {
                    // no submissions: hide if DUE date in past
                    if ($item['date'] < $now) {
                        $output['HIDEME'] = 'hideme nosubmit due-passed';
                        $output['upcoming_work_actionable_block'] = 'hideme nosubmit due-passed';
                    }
                }
            } else {
                // assess
                if ($item['max_attempts'] == 0) {
                    // 0 max attempts is unlimited: hide if FINISH in past
                    if ($item['date'] < $now) {
                        $output['HIDEME'] = 'hideme unlimited due-passed';
                        $output['upcoming_work_actionable_block'] = 'hideme unlimited due-passed';
                    }
                } elseif ($item['attempt_count'] >= $item['max_attempts']) {
                    // if limited attempts, hide if limit has been reached
                    $output['HIDEME'] = 'hideme attempts-exhausted';
                    $output['upcoming_work_actionable_block'] = 'hideme attempts-exhausted';
                } elseif ($item['date'] < $now) {
                    $output['HIDEME'] = 'hideme due-passed';
                    $output['upcoming_work_actionable_block'] = 'hideme due-passed';
                }
            }

            // skip if hideme was populated for some reason
            if (isset($output['HIDEME'])) {
                continue;
            }

            $adr = new Alaress_Date_Format($item['date'], 'U');

            if ($item['kind'] == 0) {
                $mapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
            } else {
                $mapper = new \Schoolbox\Assessment\Component\OnlineAssessmentMapper();
            }

            // how many days left, 0 <= x <= 10
            $dueWork = $mapper->findById($item['id']);
            $urgency = $dueWork->getGradientIndex();

            // override the above manipulation of output (we don't need it)
            $output = array(
                'UPCOMING_ID'      => $item['id'],
                'UPCOMING_UID'     => $this->userID,
                'UPCOMING_TITLE'   => $item['title'],
                'UPCOMING_TYPE'    => $item['type'],
                'UPCOMING_DATE'    => $adr->getRelativeTime(),
                'UPCOMING_TIME'    => $adr->isAtEndOfDay() ? $adr->getDateOnly() : $adr->getShort(),
                'UPCOMING_URGENCY' => $urgency,

                'UPCOMING_SUBJ_NAME' => $item['subj_name'],
                'UPCOMING_SUBJ_ID'   => $item['subj_id'],

                'ON_OFF' => ++$j % 2 ? '' : 'dark'
            );

            // determine actions available
            if ($item['kind'] == 0) {
                // duework
                $output['UPCOMING_HREF'] = '/submission/submissionHistory/' . $item['id'] . '/' . $this->userID;
                $output['upcoming_work_actionable_block'] = '';
                $output['showLink'] = $this->showAssessLink;
            } else {
                // assess

                /* Provide option to see previous attempts if any have been made
                 * Otherwise people should use the link in the title to resit/resume
                 * an attempt for this particular Quiz
                 */
                $attemptHandler = new \Assess_Attempt();
                $attempt = $attemptHandler->getLatestForUser($item['id'], $this->userID);
                $attemptStartTime = strtotime($attempt['start']);

                $assessMapper = new \Schoolbox\Assessment\Component\OnlineAssessmentMapper();
                $assess = $assessMapper->findById($item['id']);
                $attemptTimeRemaining = $attemptStartTime + $assess->getDuration() - time();

                $resumeAttempt = false;

                if (is_array($attempt)) {
                    if (!$attempt['finish']
                        && ($attemptTimeRemaining < 0 || $assess->getDuration() == Assess_Main::UNLIMITED_DURATION)
                    ) {
                        $resumeAttempt = true;
                    }
                }

                if ($item['attempt_count']) {
                    if ($resumeAttempt) {
                        $sectionHandler = new \Assess_Section();
                        $firstSection = $sectionHandler->getFirst($assess->getId());

                        $output['UPCOMING_HREF'] = '/assess/attempt/' . $item['id'] . '/' . $firstSection['section_id'];
                    } else {
                        $output['UPCOMING_HREF'] = '/assess/intro/' . $item['id'];
                    }

                    $output['UPCOMING_BUTTON_HREF'] = '/assess/attempts/' . $item['id'] . '/' . $this->userID;
                    $output['UPCOMING_UID'] = '';
                    $output['UPCOMING_BTN_ICON']  = 'list';
                    $output['UPCOMING_BTN_LABEL'] = '';
                } else {
                    $output['UPCOMING_HREF'] = '/assess/intro/' . $item['id'];
                    $output['upcoming_work_actionable_block'] = '';
                }
                $output['showLink'] = $this->showQuizLink;
            }

            // if the actionable flag is false, don't display the buttons
            if (!$this->actionable) {
                $output['upcoming_work_actionable_block'] = '';
            }

            $this->upcomingWork[] = $output;

            if ($this->limit > 0 && count($this->upcomingWork) >= $this->limit) {
                break;
            }
        }

        return count($this->upcomingWork) ? true : false;
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'upcomingWork')) {
            return false;
        }

        $this->setBlock($templateHandle, 'view_all_due_work', 'view_all_due_work_block');
        $this->setBlock($templateHandle, 'no_upcoming_work', 'no_upcoming_work_block');
        $this->setBlock($templateHandle, 'has_upcoming_work', 'has_upcoming_work_block');
        $this->setBlock('has_upcoming_work', 'upcoming_work', 'upcoming_work_block');
        $this->setBlock('upcoming_work', 'upcoming_work_actionable', 'upcoming_work_actionable_block');
        $this->setBlock('upcoming_work', 'upcoming_work_link', 'upcoming_work_link_block');
        $this->setBlock('upcoming_work', 'upcoming_work_nolink', 'upcoming_work_nolink_block');

        // To display the "view all" button, we must be not restricted
        if ($this->restrictedMode) {
            $this->setVar('view_all_due_work_block', '');
        } else {
            $this->parse('view_all_due_work_block', 'view_all_due_work');
        }

        if (count($this->upcomingWork)) {
            foreach ($this->upcomingWork as $output) {
                $this->setVar($output);
                if (!isset($output['upcoming_work_actionable_block'])) {
                    $this->parse('upcoming_work_actionable_block', 'upcoming_work_actionable');
                }

                // show link or no link depending on whether it's readonly
                $this->setVar('upcoming_work_link_block', '');
                $this->setVar('upcoming_work_nolink_block', '');
                if ($output['showLink']) {
                    $this->parse('upcoming_work_link_block', 'upcoming_work_link');
                } else {
                    $this->parse('upcoming_work_nolink_block', 'upcoming_work_nolink');
                }
                $this->parse('upcoming_work_block', 'upcoming_work', true);
            }

            $this->parse('has_upcoming_work_block', 'has_upcoming_work');
            $this->setVar(array(
                'no_upcoming_work_block' => ''
            ));
        } else {
            $userHandler = new User();
            $pronoun = ($this->userID == $_SESSION['id'])
                ? 'You have'
                : ($userHandler->formatName($userHandler->getByID($this->userID), '{firstname} has'));

            $this->setVar(array(
                'has_upcoming_work_block' => '',
                'upcoming_work_block' => '',
                'PRONOUN' => $pronoun
            ));

            $this->parse('no_upcoming_work_block', 'no_upcoming_work');
        }

        return true;
    }

    /**
     * Retrieves upcoming work as populated by execute().
     *
     * @return array
     */
    public function getDataFromExecute()
    {
        return $this->upcomingWork;
    }
}
