<?php
require_once __DIR__ . '/../library/Bootstrap.php';

use \Schoolbox\Collection;

use \Schoolbox\Resource\Folder\Model as Folder;
use \Schoolbox\Learning\Assessment\IAssessable;

use \Schoolbox\Learning\Course\Specification\Model as CourseSpecification;
use \Schoolbox\Learning\Course\Specification\Unit\Model as UnitSpecification;

use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\Project as Project;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as DueWorkSubmissionFilePlagscan;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use \Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use \Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;


/******************************************** BESTIARY *******************************************/


/* People
 */
$hodes = \Schoolbox\UserMapper::getInstance()->findById(230);
$sberryhill = \Schoolbox\UserMapper::getInstance()->findById(15);
$slucier = \Schoolbox\UserMapper::getInstance()->findById(16);

$bsalem = \Schoolbox\UserMapper::getInstance()->findById(31);
$asaxon = \Schoolbox\UserMapper::getInstance()->findById(17);

$celia = \Schoolbox\UserMapper::getInstance()->findBYId(3);
$somodio = \Schoolbox\UserMapper::getInstance()->findBYId(150);

/* Times
 */
$tomorrow = Alaress_Date_Format::now()->addDays(1);
$thirteenDaysFromNow = Alaress_Date_Format::now()->addDays(13);
$fourteenDaysFromNow = Alaress_Date_Format::now()->addDays(14);

$twentyDaysFromNow = Alaress_Date_Format::now()->addDays(20);
$thirtyDaysFromNow = Alaress_Date_Format::now()->addDays(30);
$fortyDaysFromNow = Alaress_Date_Format::now()->addDays(40);
$fiftyDaysFromNow = Alaress_Date_Format::now()->addDays(50);
$sixtyDaysFromNow = Alaress_Date_Format::now()->addDays(60);

/* Folders
 */
$courseSpecificationFolder = (new Folder())
    ->setId(51)
    ->setName('Houses');

$foxHouseFolder = (new Folder())
    ->setId(52)
    ->setName('Fox House');

$daltonHouseFolder = (new Folder())
    ->setId(56)
    ->setName('Dalton House');



/******************************************** CONSTRUCTION *******************************************/



/**
 * Manufacture all the IAssessables that are part of the Course Spec
 */

/* Task in a Project
 */
$task = new Task();
$task
    ->setId(1)
    ->setTitle('Read the Horadric Scrolls')
    ->setDescription('There is a book by Deckard Cain which you must read before searching for Diablo.')
    ->setCreator($celia)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($bsalem))
        ->add(new Participant($sberryhill))
        ->add(new Participant($asaxon)))
    ->setPublishAt($thirteenDaysFromNow)
    ->setOpenAt($thirteenDaysFromNow)
    ->setDueAt($fourteenDaysFromNow)
    ->setCloseAt($fourteenDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now());

$taskFeedback = new TaskFeedback();
$taskFeedback
    ->setAssessable($task)
    ->setReviewer($celia)
    ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

$taskCompletion = (new TaskCompletion);
$taskCompletion
    ->setAssessable($task)
    ->setSubmittedBy($hodes)
    ->setSubmittedOnBehalfOf($hodes)
    ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
    ->getAllFeedback()
        ->add($taskFeedback);

$task->getParticipant($hodes)->getAllResponses()->add($taskCompletion);
$task->getParticipant($hodes)->getAllFeedbacks()->add($taskFeedback);

/* Due Work in a Project
 */
$dueWork = new DueWork();
$dueWork
    ->setId(2)
    ->setTitle('Enter the Realm of Terroarrrr')
    ->setDescription('Diablo was found in the land of terroarrrrr! Find him, and whack him!')
    ->setType((new DueWorkType())
        ->setId(1)
        ->setName('Essay'))
    ->setCreator($somodio)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($bsalem))
        ->add(new Participant($sberryhill))
        ->add(new Participant($asaxon)))
    ->setPublishAt($thirteenDaysFromNow)
    ->setOpenAt($fourteenDaysFromNow)
    ->setDueAt($twentyDaysFromNow)
    ->setCloseAt($thirtyDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now())
    ->setAllFiles((new Collection())
        ->add((new DueWorkFile())
            ->setId(1)
            ->setHash('abcdefg12341234')
            ->setFilename('myfile1.jpg'))
        ->add((new DueWorkFile())
            ->setId(2)
            ->setHash('zxvzxcvzxcv945786947')
            ->setFilename('myfile2.jpg')));

$submission = new DueWorkSubmission();
$submission
    ->setAssessable($dueWork)
    ->setSubmittedBy($hodes)
    ->setSubmittedOnBehalfOf($hodes)
    ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
    ->getAllFiles()->add((new DueWorkSubmissionFile())
        ->setId(11)
        ->setStorage((new \Schoolbox\Resource\Storage\Model())
            ->setHash('s89789d9hw3thsug8923u5'))
        ->setCrocodocId('vjfh-6677=eirj-01928333')
        ->setFilename('mark this Sir.xlsx')
        ->setPlagscan((new DueWorkSubmissionFilePlagscan())
            ->setId(1234)
            ->setStatus(1)
            ->setScore('93% plagiariased')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>')))
    ->add((new DueWorkSubmissionFile())
        ->setId(12)
        ->setStorage((new \Schoolbox\Resource\Storage\Model())
            ->setHash('lk5j34kl5jousdio'))
        ->setCrocodocId('vjfh-6677=eirj-01928333')
        ->setFilename('and this too.docx'));

$feedback = (new DueWorkFeedback())
    ->setAssessable($dueWork)
    ->setNormalisedMark(-3)
    ->setComment('This is a flawed piece of work.')
    ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT))
    ->setAllFiles((new Collection())
        ->add((new DueWorkFeedbackFile())
            ->setId(12)
            ->setHash('lj23lj2llj5l2jsdkjf90')
            ->setCrocodocId('j239jf-23mndi9f-1234nns9fh-5nl6i0f')
            ->setFilename('This is the correct response.txt')));

$submission
    ->getAllFeedback()->add($feedback);

$dueWork->getParticipant($hodes)->getAllResponses()->add($submission);
$dueWork->getParticipant($hodes)->getAllFeedbacks()->add($feedback);

/* Quiz in a Project
 */
$quiz = (new Quiz())
    ->setId(3)
    ->setTitle('How did you find The Prime Evil?')
    ->setDescription('A quick pop quiz about how you died your way to Diablo.')
    ->setCreator($somodio)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($bsalem))
        ->add(new Participant($sberryhill))
        ->add(new Participant($asaxon)))
    ->setPublishAt($twentyDaysFromNow)
    ->setOpenAt($twentyDaysFromNow)
    ->setDueAt($thirtyDaysFromNow)
    ->setCloseAt($fortyDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now());

$project = new Project();
$project
    ->setTitle('Find Diablo')
    ->setDescription('The things you need to do in order to slay the prime evil')
    ->setCreator($somodio)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($bsalem))
        ->add(new Participant($sberryhill))
        ->add(new Participant($asaxon)))
    ->setPublishAt($tomorrow)
    ->setOpenAt($tomorrow)
    ->setDueAt($fiftyDaysFromNow)
    ->setCloseAt($fiftyDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now())
    ->setAssessables((new Collection())
        ->add($task)
        ->add($dueWork)
        ->add($quiz));

$otherTask = (new Task())
    ->setTitle('Self-evaluation')
    ->setDescription('Write about your journeys fulfilling the Project prophecy')
    ->setCreator($celia)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($sberryhill)))
    ->setPublishAt($tomorrow)
    ->setOpenAt($tomorrow)
    ->setDueAt($fiftyDaysFromNow)
    ->setCloseAt($fiftyDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now());

$otherDueWork = (new DueWork())
    ->setTitle('Essay on your journeys fulfilling the Project prophecy')
    ->setDescription('Plaid gluten-free thundercats truffaut literally cray bitters kinfolk mlkshk, skateboard everyday carry. Gluten-free chicharrones art party fashion axe, chillwave four loko offal wayfarers tacos celiac shoreditch church-key artisan XOXO marfa')
    ->setType((new \Schoolbox\Learning\Assessment\DueWork\Type())
        ->setId(1)
        ->setName('Essay'))
    ->setCreator($celia)
    ->setAllParticipants((new Collection())
        ->add(new Participant($hodes))
        ->add(new Participant($asaxon)))
    ->setPublishAt($fiftyDaysFromNow)
    ->setOpenAt($fiftyDaysFromNow)
    ->setDueAt($sixtyDaysFromNow)
    ->setCloseAt($sixtyDaysFromNow)
    ->setCreatedAt(Alaress_Date_Format::now())
    ->setUpdatedAt(Alaress_Date_Format::now());

/* Manufacture Course Specification
 * Add the Course Spec back to the Folder - weird cyclic reference
 */
$courseSpecification = new CourseSpecification();
$courseSpecification
    ->setFolder($courseSpecificationFolder)
    ->getUnits()
        ->add((new UnitSpecification())
            ->setFolder($foxHouseFolder)
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setAssessables((new Collection())
                ->add($project)))
        ->add((new UnitSpecification())
            ->setFolder($daltonHouseFolder)
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setAssessables((new Collection())
                ->add($otherTask)
                ->add($otherDueWork)));

$courseSpecificationFolder
    ->setCourseSpecification($courseSpecification);



/******************************************** Let's have a look inside *******************************************/



echo '<fieldset><legend>Course Details</legend>';
new \Alaress_Probe('<strong>Folder</strong>', '<strong>numUnits</strong>');
new \Alaress_Probe($courseSpecification->getFolder()->getName(), $courseSpecification->getUnits()->count());
echo '</fieldset><br /><br />';

echo '<fieldset><legend>Unit Composition</legend>';
new \Alaress_Probe('<strong>Unit Name</strong>');

foreach ($courseSpecification->getUnits() as $currentUnit) {
    dumpAssessable($currentUnit, 4);
}

echo '</fieldset><br /><br />';




/**
 * Dump Assessable data for a Unit
 *
 * @param UnitSpecification $unit
 */
function dumpAssessable(UnitSpecification $unit, $indents = 0)
{
    new \Alaress_Probe("{$unit->getFolder()->getName()} ({$unit->getAssessables()->count()} assessables)");

    foreach ($unit->getAssessables() as $currentAssessable) {
        new \Alaress_Probe(str_pad('', $indents) . "+ {$currentAssessable->getTitle()} ({$currentAssessable->getTypeName()})");

        /* Show all the shenanigans inside the proj
         */
        if ($currentAssessable instanceof Project) {
            foreach ($currentAssessable->getAssessables() as $currentProjectAssessable) {
                new \Alaress_Probe(str_pad('', $indents + 4) . "o {$currentProjectAssessable->getTitle()} ({$currentProjectAssessable->getTypeName()})");

                dumpParticipant($currentProjectAssessable, $indents + 8);
            }
        }
        /* Otherwise just deal with the assessable normally
         */
        else {
            dumpParticipant($currentAssessable, $indents + 4);
        }
    }
}

/**
 * Dump Participant data for an IAssessable
 *
 * @param IAssessable $assessable
 * @param int $indents
 */
function dumpParticipant(IAssessable $assessable, $indents = 0)
{
    /** @var Participant $currentParticipant */
    foreach ($assessable->getAllParticipants() as $currentParticipant) {
        new \Alaress_Probe(str_pad('', $indents) . "@ {$currentParticipant->getUser()->getFullname()}");

        dumpResponse($currentParticipant, $assessable, $indents + 4);
        dumpFeedback($currentParticipant, $assessable, $indents + 4);
    }
}


/**
 * Dump Response data
 *
 * @param Participant $participant
 * @param IAssessable $assessable
 * @param int $indents
 */
function dumpResponse(Participant $participant, IAssessable $assessable, $indents = 0)
{
    $allResponses = $participant->findAllResponsesForAssessable($assessable);

    if ($allResponses->count()) {
        $response = $allResponses->first();

        new \Alaress_Probe(str_pad('', $indents) . "submission: Submitted {$response->getSubmittedAt()->getLocal()}");

        if ($response instanceof DueWorkSubmission) {
            foreach ($response->getAllFiles() as $currentFile) {
                new \Alaress_Probe(str_pad('', $indents+4) . "file: {$currentFile->getFilename()}");
            }
        }
    }
}


/**
 * Dump Feedback data
 *
 * @param Participant $participant
 * @param IAssessable $assessable
 */
function dumpFeedback(Participant $participant, IAssessable $assessable, $indents = 0)
{
    $allFeedback = $participant->findAllFeedbackForAssessable($assessable);
    $mark = null;
    $feedback = null;

    if ($allFeedback->count()) {
        $feedback = $allFeedback->first();

        $markValue = $feedback->calculateMark();

        if ($assessable instanceof Task) {
            $mark = "feedback: <em>{$feedback->getComment()}</em> [Completed]";
        }
        else {
            $mark = "feedback: <em>{$feedback->getComment()}</em> [{$markValue}]";
        }
    }

    if ($mark) {
        new \Alaress_Probe(str_pad('', $indents + 4) . $mark);

        if ($feedback && $feedback instanceof DueWorkFeedback) {
            foreach ($feedback->getAllFiles() as $currentFile) {
                new \Alaress_Probe(str_pad('', $indents + 8) . "file: {$currentFile->getFilename()}");
            }
        }
    }
}













die();