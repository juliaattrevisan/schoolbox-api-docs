<?php
/**
 * View and grade students submissions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as SubmissionMapper;
use \Schoolbox\Assessment\Component\SubmissionBoxMapper;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use \Schoolbox\Assessment\Activity;
use Schoolbox\Resource\Attachment\Zone;
use \Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use \Schoolbox\UserMapper;
use \Schoolbox\Collection;
use \Crocodoc\Renderer\Factory;
use Schoolbox\Breadcrumbs;

/** @var SitePage $this */

if (count($this->arg) < 2) {
    $this->error(2); // not enough info
}

/* /submission/inlineGrading/view/<box_id>/<user_id>
 */
$submissionBoxId = $this->arg[0];
$currentUid = $this->arg[1];

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

try {
	$submissionBoxMapper = new SubmissionBoxMapper();
	$submissionBox       = $submissionBoxMapper->findById($submissionBoxId);
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(17);
	exit();
}

// Security check

if (!$submissionBox->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
	error(25);
}

/* This is an optional argument
 */
$submissionId = $this->arg[2];

$term          = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate($submissionBox->getDueDate());

/* Add category codes
 */
$categoryCodeMapper = new FolderCodeMapper();
$categoryCode = $categoryCodeMapper->findForCategory($submissionBox->getCategory()->getId());
$submissionBox->getCategory()->setCodes($categoryCode);

/* Crappy handler
 */
$submissionHandler = new Submission();
$studentList = $submissionHandler->getAllParticipantsForSubmission($submissionBox);

/* Uniqueify list (why this returns dupes, I'm not entirely sure)
 */
$processed = array();
$stack = array();

$this->setFile(TEMPLATE_content, 'submission/inlineGrading/view.html');
$this->setBlock(TEMPLATE_content, 'download_annotated', 'download_annotated_block');
$this->setBlock(TEMPLATE_content, 'submit_student', 'submit_student_block');
$this->setBlock(TEMPLATE_content, 'submit_button', 'submit_button_block');
$this->setVar('download_annotated_block', '');

$this->setFile('studentNavTpl', 'submission/inlineGrading/injectables/controls/navigation-option.html');
$this->setBlock('studentNavTpl', 'marked_submitted', 'marked_submitted_block');
$studentNav = [];

$dueWorkDetail = new DueWork_Detail($submissionBox);
$dueWorkDetail->processStudents($studentList);

foreach ($dueWorkDetail->getSitepageData() as $studentId => $studentData) {
	if (!in_array($studentId, $processed)) {
		$processed[] = $studentId;

        /** @var Schoolbox\User $currentStudent */
        $currentStudent = $studentData['user']['instance'];

        /** @var Alaress_Date_Format $submitTime */
        $submitTime  = $studentData['user']['time'];
        $submitText  = $studentData['user']['status'];
        $submitClass = $studentData['user']['class'];

        unset($studentData['user']);

        $submit = count($studentData['submissions']) > 0;
        $ontime = false;
        if ($submit) {
            $ontime = $submitClass == DueWork_Detail::CLASS_ON_TIME;
        }
        $marked = isset($studentData['return']['id']) && $studentData['return']['id'] > 0;

        if ($marked) {
            $markTime = new Alaress_Date_Format($studentData['return']['returned'], 'Y-m-d H:i:s');
        }

        $this->setVar(array(
            'BOX_ID'                   => $submissionBox->getId(),
            'BOX_GRADIENT'             => $submissionBox->getGradientIndex(),
            'FOLDER_ID'                => $submissionBox->getCategory()->getId(),
            'FOLDER_NAME'              => $submissionBox->getCategory()->getName(),
            'GRADE_VISIBILITY'         => $submissionBox->getMarkVisibilityDescription(),
            'GRADE_VISIBILITY_CODE'    => $submissionBox->getMarkVisibility(),
            'GRADE_VISIBILITY_CLASS'   => \Schoolbox\Assessment\Widgets::getCss($submissionBox->getMarkVisibility()),
            'USER_ID'                  => $currentStudent->getId(),
            'USER_EXT_ID'              => $currentStudent->getExternalId(),
            'USER_FULLNAME'            => $currentStudent->formatName('{lastname}, {firstname}'),
            'USER_UNSUBMITTED'         => $submit ? 'false' : 'true',
            'USER_MARKSTATUS_CLASS'    => $marked ? 'complete' : 'pending',
            'USER_MARKSTATUS_DATETIME' => $marked ? $markTime->getLocal() : '',
            'USER_SUBMISSION_CLASS'    => $submit ? ($ontime ? 'submitted' : 'submitted-late') : 'unsubmitted',
            'USER_SUBMISSION_TEXT'     => $submit ? ($ontime ? 'Submitted on Time' : 'Submitted Late') : 'Not Submitted',
        ));

        if (!$marked) {
            $this->setVar('marked_submitted_block', '');
        } else {
            $this->parse('marked_submitted_block', 'marked_submitted');
        }

        if($submissionBox->getAcceptOnline()) {
            $this->parse('submit_student_block', 'submit_student');
        }
        else {
            $this->setVar('submit_student_block', '');
        }

        $this->setBlock('studentNavTpl', 'show_status', 'show_status_block');

        if (!$submissionBox->getAcceptOnline()) {
            $this->setVar('show_status_block', '');
        } else {
            $this->parse('show_status_block', 'show_status');
        }

        // parse template with PHPLib, shuck it out then put it into JSON? Whoa
        $this->parse('USER_HTML', 'studentNavTpl');

		$studentNav[] = [
            'text'  => $currentStudent->formatName('{lastname}, {firstname}'),
            'value' => "/submission/inlineGrading/view/{$submissionBox->getId()}/{$currentStudent->getId()}",
            'html'  => $this->getVar('USER_HTML'),
            'unsubmitted' => $submit ? false : true,
        ];

		/* Augment navigation data
		 */
		$augmented = array(
			'currentStudent' => $currentStudent,
			'submission' => null,
			'navigation' => array(
				'id' => $currentStudent->getId(),
				'label' => $currentStudent->getLastname() . ', ' . $currentStudent->getFirstname(),
				'value' => '/submission/inlineGrading/view/' . $submissionBoxId . '/' . $currentStudent->getId()
			),
		);

		$stack[] = $augmented;
	}
}
$this->setVar('STUDENTNAV_JSON', json_encode($studentNav));

/* Trim out only this student
 */
$submissionMapper = new SubmissionMapper();

for ($i=0; $i < count($stack); $i++) {
	$currentAugmented = $stack[$i];

	if ($currentAugmented['currentStudent']->getId() == $this->arg[1]) {

		/* Load a specific submission for this box/person
		 */
		if (is_numeric($this->arg[2])) {
			$submission = $submissionMapper->findById($this->arg[2]);

			if ($submission) {
				$currentAugmented['submission'] = $submission;
			}
		}
		else {
			$collection = $submissionMapper->getHistory($currentAugmented['currentStudent']->getId(), $submissionBoxId);

			/* Get the latest submission, if one exists
			 */
			if ($collection->count()) {
				$currentAugmented['submission'] = $collection->first()->getSubmission();
                $this->arg[2] = $collection->first()->getSubmission()->getId();
			}
		}

		/* Previous
		 */
		$previousStudent = $stack[$i-1];

		/* Next
		 */
		$nextStudent = $stack[$i+1];

		break;
	}
}

/* Rendering things out
 */
$userMapper = new UserMapper();
$user = $userMapper->findById($this->arg[1]);

/* Find all returned work for this student
 */
$returnWorkMapper   = new ReturnedWorkMapper();
$allReturnedWork    = new Collection();
$returnedActivities = $returnWorkMapper->getHistory($user->getId(), $submissionBox->getId());
foreach ($returnedActivities as $activity) {
    if ($activity instanceof Activity) {
        $allReturnedWork->add($activity->getReturnedWork());
    }
}

/* Augment array with data
 */
if ($currentAugmented['submission'] != null) {
	$submissionId = $currentAugmented['submission']->getId();

	$api_token = trim(Config::get('crocodoc_api_token'));

	if (!empty($submissionId) && !empty($api_token) && !empty($currentAugmented['submission']->getUuid())) {
		$this->parse('download_annotated_block', 'download_annotated');
	}
}

/* Data for the navigation selector
 */
$navigationItems = json_encode($stack);

$navigationElements = array(
	'boxId' => $submissionBox->getId(),
	'previous' => $previousStudent,
	'current' => $currentAugmented['currentStudent'],
	'next' => $nextStudent,
);

$navigationDirect = json_encode($navigationElements);

if (!$term->isMarkableBy($_SESSION['user'])) {
	$this->setVar('ATTACH_ZONE', '');
} else {
	$zone = new Zone('inline-return');

	$zone->setMulti(true)
		->setMimeFilter('*/*')
		->setThumbnailSize(64)
		->setAttachPrompt('Attach file to return')
		->setModalTitle('Select file')
		->setAttachmentsLabel('');

	$zone->renderIntoSitePage($this, 'ATTACH_ZONE');
}

/* Render injectable views
 */
$templateFactory = new Factory($this, $currentAugmented['submission'], $allReturnedWork, $navigationElements, $term);
$renderer = $templateFactory->produce();
$renderer->render();

/* Assign marks
 */
$oneStudent = new Collection();
$oneStudent->add($currentAugmented['currentStudent']);

$dueWorkDetail->processStudents($oneStudent);
$dueWorkDetail->renderDetails($this, DueWork_Detail::INLINE_GRADER);

$this->setTitle('Grading '.Scrub::text($submissionBox->getName()).' for '.Scrub::text($user->getFullName()));

/* Determine redirect based on whether you're at the end of the list or not
 * Personally I think it'd be better to wrap back to the start, instead of
 * getting kicked out - then you can review the marks in a 'horizontal' manner
 */
if (is_array($nextStudent)) {
	$nextUrl = '/submission/inlineGrading/view/' . $submissionBox->getId() . '/' . $nextStudent['currentStudent']->getId();
}
else {
	$nextUrl = '/submission/viewSubmission/' . $submissionBox->getId();
}

$this->setVar(array(
	'INLINE_GRADING_SUBMISSION_ID' => $submissionId,
	'INLINE_GRADING_SUBMISSION_BOX_ID' => $submissionBox->getId(),
	'INLINE_GRADING_SUBMISSION_BOX_NAME' => Scrub::text($submissionBox->getName()),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_CURRENT_ID' => $currentAugmented['currentStudent']->getId(),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_DIRECT' => $navigationDirect,
	'INLINE_GRADING_SUBMISSION_NAVIGATION_ITEMS' => $navigationItems,
	'INLINE_GRADING_STUDENT_FULL_NAME' => Scrub::text($user->getFullName()),
	'INLINE_GRADING_STUDENT_ID' => $user->getId(),
    'INLINE_GRADING_STUDENT_EXTERNAL_ID' => $user->getExternalId(),
	'INLINE_GRADING_SUBMISSION_NAVIGATION_NEXT_URL' => $nextUrl
));

/* Skip Unsubmitted checkbox
 * Find out who should be skipped and pass them to the js
 */
if ($submissionBox->getSkipUnsubmitted()) {

	/* Remember everyone who hasn't submitted
	 */
	$noSubmissions = array();

	/* Find all the submissions to this submission box
	 */
	$allSubmissions = $submissionMapper->findByColumn('box_id', $submissionBox->getId());

	/* Get a unique list of users who submitted to this box
	 */
	$ids = array();

	foreach($allSubmissions as $currentSubmission) {
		$ids[$currentSubmission->getOwner()->getId()] = '';
	}

	$ids = array_keys($ids);

	/* Find all the people from the hybrid list who didn't exist in $ids
	 */
	if (count($ids)) {
		foreach($stack as $current) {
			if (!in_array($current['currentStudent']->getId(), $ids)) {
				$noSubmissions[] = $current['currentStudent']->getId();
			}
		}
	}

	/* Parse block
	 */
	$this->setVar(array(
		'SKIP_UNSUBMITTED_CHECKED' => 'checked="checked"',
		'INLINE_GRADING_SUBMISSION_NAVIGATION_DISABLED' => json_encode($noSubmissions),
	));
}
/* Otherwise nobody is disabled
 */
else {
	$this->setVar(array(
		'SKIP_UNSUBMITTED_CHECKED' => '',
		'INLINE_GRADING_SUBMISSION_NAVIGATION_DISABLED' => json_encode(array()),
	));
}

if (!$term->isMarkableBy($user)) {
	$this->setVar('submit_button_block', '');
} else {
	$this->parse('submit_button_block', 'submit_button');
}

/* Render out breadcrumbs
 */
$trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => 'Submissions',
        'href' => "/submission/listSubmission",
        'class' => '',
    ],
    [
        'text' => $submissionBox->getCategory()->getName(),
        'href' => "/submission/listSubmission/{$term->getCode()}/{$submissionBox->getCategory()->getId()}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($submissionBox->getName()),
        'href' => "/submission/viewSubmission/{$submissionBox->getId()}",
        'class' => '',
    ],
    [
        'text' => Scrub::text($user->getFullName()),
        'href' => "/search/user/{$user->getId()}",
        'class' => 'active',
    ]
]);

/* Rubric's Cube
 */
// do fun rubric things
$this->setBlock(TEMPLATE_content, 'rubric', 'rubric_block');

if ($submissionBox->isRubric()) {

	$mtSlug = $submissionBox->getMarkType()->getSlug();

    $context = [
	    'rubric'   => RubricMapper::getInstance()->findBySubmissionBoxAndStudent($submissionBox, $user)->toContext(),
	    'markType' => [
		    'mode' => $mtSlug
	    ]
    ];


	if ($mtSlug == 'letter') {
		$group = $submissionBox->getGradeGroup();

		if (!$group->getGradeLevels()->count()) {
			// insufficient hydration
			$group->setGradeLevels(
				Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper::getInstance()
                  ->findByColumn(
                      'submission_grade_group_id',
                      $group->getId(),
                      [ 'order' => [ 'lower_bound' => 'ASC' ] ]
                  )
			);
		}

		$levels = [];

		foreach ($group->getGradeLevels() as $level) {
			$levels[$level->getLowerBound()] = $level->getCaption();
		}

		$context['markType']['conf'] = $levels;
	}
	$template = !$term->isMarkableBy($_SESSION['user']) ? '@rubric/grid.twig' : '@rubric/grid-markable.twig';
	$this->setVar('INJECTED_MARK_RUBRIC', $this->getTwig()->render($template, $context));
} else {
    $this->setVar('INJECTED_MARK_RUBRIC', '');
}
