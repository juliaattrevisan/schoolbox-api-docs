<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;

use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Exception as AssessmentException;
use Schoolbox\Assessment\StudentReportCard\ContextSelector;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * This class relies on handlers, because there isn't time to
 * implement the necessary functionality into mappers at this point.
 * Class Marksheet
 *
 * @package Schoolbox\Assessment
 */
class Marksheet
{

    /** @var Category */
    protected $category;
    /** @var User */
    protected $sessionUser;

    /** @var \User */
    protected $userHandler;
    /** @var \Group */
    protected $groupHandler;

    /** @var \External_Class */
    protected $synergyHandler;

    /** @var \Submission */
    protected $submissionHandler;
    /** @var \SubmissionStudent */
    protected $submissionStudentHandler;
    /** @var \SubmissionReturn */
    protected $returnHandler;
    /** @var \Marksheet_Factory */
    protected $marktypeFactoryHandler;

    /** @var \Assess_Factory */
    protected $assessFactoryHandler;
    /** @var \Assess_Main */
    protected $assessHandler;
    /** @var \Assess_Attempt */
    protected $attemptHandler;
    /** @var \Assess_Section */
    protected $sectionHandler;
    /** @var \Assess_Response */
    protected $responseHandler;

    /** @var CategoryMapper */
    protected $categoryMapper;
    /** @var UserMapper */
    protected $userMapper;
    /** @var OnlineAssessmentMapper */
    protected $oaMapper;
    /** @var SubmissionBoxMapper */
    protected $sbMapper;
    /** @var \DueWork_ErrorMapper */
    protected $errorMapper;

    /** @var IConfigLoader */
    protected $conf;

    /** @var array */
    protected $studentDataSet = array();
    /** @var array */
    protected $statisticDataSet = array();
    /** @var array */
    protected $displayOrder = array();
    /** @var array */
    protected $gradeGroups = array();
    /** @var Collection */
    protected $errorList;

    /** @var int */
    protected $year;
    /** @var int */
    protected $semester;


    /**
     * @param int   $categoryId
     * @param int   $sessionUserId
     * @param array $handlerDependencies
     * @param array $mapperDependencies
     * @param       $conf
     *
     * @throws AssessmentException
     * @throws ResourceException
     */
    public function __construct($categoryId, $sessionUserId, $handlerDependencies, $mapperDependencies, $conf)
    {

        $this->conf = $conf;

        // ultimately, this class should be refactored to replace handler dependencies with mapper dependencies
        foreach ($handlerDependencies as $key => $handler) {
            $prop = $key . 'Handler';
            if (property_exists($this, $prop)) {
                $this->$prop = $handler;
            }
        }

        foreach ($mapperDependencies as $key => $mapper) {
            $prop = $key . 'Mapper';
            if (property_exists($this, $prop)) {
                $this->$prop = $mapper;
            }
        }

        if (!$this->groupHandler->isStaff()) {
            throw new AssessmentException('Permission Denied', 19);
        }

        $this->category    = $this->categoryMapper->findById($categoryId);
        $this->sessionUser = $this->userMapper->findById($sessionUserId);

        $this->errorList = $this->errorMapper->findAll();
    }

    /**
     * Generate the data from the given category and session user
     */
    public function generateData($year, $semester)
    {

        $catId          = $this->category->getId();
        $this->year     = $year;
        $this->semester = $semester;

        /** @var Collection|User[] $studentCollection */
        $studentCollection = new Collection();

        // Get students from the external DB, and put into student collection
        if ($this->category->getCodes()->count() && $this->conf->get('external_enable')) {
            $externalList = $this->synergyHandler->getStudents($this->category->getCodeString());
            if (is_array($externalList)) {
                foreach ($externalList as $studentData) {
                    $extIds[] = $studentData['synergy_id'];
                }
                $studentCollection->merge($this->userMapper->findByColumn('synergy_id', $extIds));
            }
        }

        // Get all users who are in the group, and add to collection
        $studentList = array();
        $groupList   = $this->groupHandler->getMemberList($this->category->getId());
        if (is_array($groupList)) {
            $studentList = array_merge($studentList, $groupList);
        }

        // Get list of extra users for each work item
        // Online assessments: get all users who have attempted assessment
        // Due work: all users who have submitted or returned work
        $workList = $this->submissionHandler->getAllForUserCategory($catId, null, $year, $semester);
        if (is_array($workList)) {
            foreach ($workList as $work) {
                if ($work['type_id'] == \Assess_Main::ONLINE_ASSESSMENT_TYPE_ID) {
                    $studentList = array_merge(
                        $studentList,
                        $this->attemptHandler->getStudentListByAssessId($work['id'])
                    );
                } else {
                    $studentList = array_merge(
                        $studentList,
                        $this->submissionStudentHandler->getStudentListByBoxId($work['id']),
                        $this->returnHandler->getStudentListByBoxId($work['id'])
                    );
                }
            }
        }

        // convert student ID list to a collection of user objects
        $extIds = array();
        foreach ($studentList as $studentData) {
            $extIds[] = $studentData['uid'];
        }
        $studentCollection->merge($this->userMapper->findByColumn('id', $extIds));

        // Remove duplicates from $studentCollection
        $newCollection = new Collection();
        $addedIds      = array();
        foreach ($studentCollection as $student) {
            $studentId = $student->getId();
            if (!array_key_exists($studentId, $addedIds)) {
                $newCollection->add($student);
                $addedIds[$studentId] = true;
            }
        }
        $studentCollection = $newCollection;
        unset($newCollection);

        if (is_array($workList)) {
            $statData = array();
            $order    = array();

            foreach ($workList as $work) {
                $isOA = $work['type_id'] == \Assess_Main::ONLINE_ASSESSMENT_TYPE_ID ? 1 : 0;

                if ($isOA) {
                    $mapper = $this->oaMapper;
                    $mtSlug = 'percent';
                } else {
                    $mapper = $this->sbMapper;
                    $mtSlug = $work['slug'];
                }

                $stats      = $mapper->getStatistics($work['id']);
                $object     = $mapper->findById($work['id']);
                $weight     = $object->getWeight();
                $visibility = $object->getMarkVisibility();

                $progress = $mapper->getProgress($work['id']);

                // use the accepts_submissions "flag" to determine this field's output
                if (!$progress['accepts_submissions']) {
                    $progress['submission_count'] = '&ndash;';
                }
                unset($progress['accepts_submissions']);

                // this is a bit hacky, but it concisely gets the data into the desired format
                foreach ($progress as &$p) {
                    if (is_numeric($p)) {
                        $p .= ' / ' . $studentCollection->count();
                    }
                }

                $extraParams = array();
                if ($object instanceof SubmissionBox) {
                    $maxMark = $object->getMaxMark();
                    if ($object->getMarkType()->getId() == SubmissionBox\MarkType::TYPE_LETTER) {
                        $extraParams['gradeGroup'][] = $this->gradeGroups[] = $object->getGradeGroup()->getId();
                    }
                } elseif ($object instanceof OnlineAssessment) {
                    $maxMark = 100;
                }

                if (is_null($stats['mean'])) {
                    $classAvg = null;
                } else {
                    $classAvg = $this->marktypeFactoryHandler->build(
                        $mtSlug,
                        ($object instanceof SubmissionBox ? $object : new SubmissionBox())
                    )->convertRawMark($stats['mean'], $maxMark, true, false);
                }


                $statData[$isOA][$work['id']] = array_merge(
                    array(
                        'avg'        => $classAvg,
                        'weight'     => $weight,
                        'visibility' => $visibility,
                    ),
                    $stats,
                    $progress
                );

                /** @var $end \Alaress_Date_Format */
                $end   = $isOA ? $object->getFinish() : $object->getDueDate();
                $stamp = $end->getUnixTimestamp();

                // effectively, this sets the order of the columns by chronology, then order created if collisions
                while (array_key_exists($stamp, $order)) {
                    ++$stamp;
                }
                $order[$stamp] = array(
                    'isOA'   => $isOA,
                    'id'     => $work['id'],
                    'object' => $object
                );
                ksort($order);

            }
            unset($work);

            $this->statisticDataSet = $statData;
            $this->displayOrder     = $order;
        }

        if ($studentCollection->count()) {
            // Students are always sorted by surname, then first name
            $studentCollection->sortByCallback(
                function ($a, $b) {
                    /** @var User $a */
                    /** @var User $b */
                    $cmp = strcmp($a->getLastname(), $b->getLastname());
                    if ($cmp === 0) {
                        $cmp = strcmp($a->getFirstname(), $b->getFirstname());
                    }
                    return $cmp;
                }
            );

            $studentGrades = Grades::getInstance()->getStudentGradesForSubject(
                $studentCollection,
                $this->category,
                $this->year,
                $this->semester
            );

            foreach ($studentCollection as $student) {
                /** @var $student User */
                $userId = $student->getId();

                // exclude if not a a valid user
                try {
                    $row['student'] = $student;
                } catch (MapperException $e) {
                    continue;
                }

                if (is_array($workList)) {
                    foreach ($workList as $work) {
                        $isOA = $work['type_id'] == \Assess_Main::ONLINE_ASSESSMENT_TYPE_ID ? 1 : 0;

                        if ($isOA) {
                            $mark = $this->getOnlineAssessMarkForStudent($work['id'], $userId);
                        } else {
                            $mark = $this->getSubmissionBoxMarkForStudent($work['id'], $userId);
                        }
                        $row['marks'][$isOA][$work['id']] = $mark;
                        $row['avg']                       = count($studentGrades[$userId]['grades']) ? $studentGrades[$userId]['avg'] : null;
                    }
                    unset($work);
                }

                $this->studentDataSet[$userId] = $row;
            }
            unset($student);
        }
    }

    /**
     * Calculate the mark for the most recent attempt of the given OA by the given student
     *
     * @param int $assessId
     * @param int $studentId
     *
     * @return array
     */
    protected function getOnlineAssessMarkForStudent($assessId, $studentId)
    {
        $mostRecentAttempt = $this->attemptHandler->getLatestForUser($assessId, $studentId);
        if (!$mostRecentAttempt) {
            return array(
                'mark'      => '',
                'norm_mark' => null
            );
        }
        if (!$mostRecentAttempt['finish']) {
            return array(
                'mark'        => '',
                'norm_mark'   => null,
                'in_progress' => true,
            );
        }
        $marks = $this->attemptHandler->getMarksForAttempt($mostRecentAttempt['attempt_id']);
        $total = $this->attemptHandler->getTotalMarks($assessId, $mostRecentAttempt['attempt_id']);

        $output = array('mark' => '', 'norm_mark' => '');

        if (!$total) {
            return $output;
        }

        $output['mark'] = (float)(number_format($marks, 2)) . ' / ' . (float)(number_format($total, 2));
        if (is_numeric($total) && ($total > 0)) {
            $output['norm_mark'] = $marks / $total;
        } else {
            $output['norm_mark'] = $marks;
        }

        return $output;
    }

    protected function getSubmissionBoxMarkForStudent($boxId, $studentId)
    {

        $rtn = $this->returnHandler->getByUser($studentId, $boxId, 1);

        if (isset($rtn[0]['mark'])) {
            $mark = array(
                'mark'      => $rtn[0]['mark'],
                'norm_mark' => $rtn[0]['norm_mark'],
                'comment'   => $rtn[0]['comment'],
            );
        } else {
            $mark = array(
                'mark'      => null,
                'norm_mark' => null,
                'comment'   => ($rtn[0]['comment'] ?: ''),
            );
        }

        if (is_null($mark['mark'])) {
            $mark['mark'] = '';
        } else {
            $error = $this->errorList->getBy('displayAs', $mark['mark']);
            if (!is_null($error)) {
                $mark['mark'] = $error->getCode();
            }
        }

        return $mark;
    }


    /**
     * Render the table in the given sitepage
     *
     * @param \SitePage $page
     */
    public function renderSitepage($page)
    {

//		var_dump($this->statisticDataSet);
//		var_dump($this->studentDataSet);
//		var_dump($this->displayOrder);

        $contextSelector = new ContextSelector('submission/markSheet');

        // generate a "past 6 years" array
        for ($i = 5; $i >= 0; $i--) {
            $years[] = date('Y') - $i;
        }
        $contextSelector->setYears($years);
        $contextSelector->setCategoryFirst(true);

        $catIdList = array();

        $allSubjects = $this->groupHandler->involvedInSubjects($this->sessionUser->getId());

        if (is_array($allSubjects) && count($allSubjects)) {
            foreach ($allSubjects as $catAsArray) {
                $catIdList[] = $catAsArray['id'];
            }
        }

        $contextSelector->setCategories($this->categoryMapper->findByColumn('id', $catIdList));

        $contextSelector->render(
            $page, 'CONTEXT_SELECTOR', array(
            'year'     => $this->year,
            'semester' => $this->semester,
            'category' => $this->category->getId()
        )
        );

        $page->setFile(TEMPLATE_content, 'submission/markSheet.html');
        $page->setTitle('Marksheet for ' . $this->category->getName());
        $page->setTab('submission');

        // set global vars
        $codeString = $this->category->getCodeString();
        $page->setVar(
            array(
                'CATEGORY'      => $this->category->getName(),
                'CID'           => $this->category->getId(),
                'CATEGORY_CODE' => strlen($codeString) > 0
                    ? ('('.\Scrub::text($codeString).')')
                    : '',
                'WORK_YEAR'     => $this->year,
                'WORK_SEMESTER' => $this->semester
            )
        );

        $trail = new \Schoolbox\Breadcrumbs();
        $trail->renderIntoSitePage($page, 'BREADCRUMB', [
            [
                'text' => 'Submissions',
                'href' => "/submission/listSubmission",
                'class' => '',
            ],
            [
                'text' => \Scrub::text($this->category->getName().' '.( strlen($codeString) > 0 ? ('('.$codeString.')') : '' )) ,
                'href' => "/submission/listSubmission/{$this->year}/{$this->semester}/".$this->category->getId(),
                'class' => '',
            ],
            [
                'text' => 'Marksheet',
                'href' => '#',
                'class' => 'active',
            ]
        ]);


        // define blocks
        $page->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
        $page->setBlock(TEMPLATE_content, 'year_option', 'year_option_block');

        $page->setBlock(TEMPLATE_content, 'work_header', 'work_header_block');
        $page->setBlock(TEMPLATE_content, 'class_average', 'class_average_block');

        $page->setBlock(TEMPLATE_content, 'stat', 'stat_block');
        $page->setBlock('stat', 'stat_item', 'stat_item_block');
        $page->setBlock('stat_item', 'stat_editable', 'stat_editable_block');
        $page->setBlock('stat_item', 'stat_visible', 'stat_visible_block');
        $page->setBlock('stat_item', 'stat_readonly', 'stat_readonly_block');
        $page->setBlock('stat_visible', 'stat_visibility_drop', 'stat_visibility_drop_block');

        $page->setBlock(TEMPLATE_content, 'student_row', 'student_row_block');
        $page->setBlock('student_row', 'student_work', 'student_work_block');

        // if mapping not defined here, uses ucfirst(:field)
        $statLabelLookup = array(
            'avg'              => 'Class Average',
            // this is different to mean: same input value, but formatted according to the work marktype
            'stdev'            => 'Standard Deviation',
            'min'              => 'Minimum',
            'max'              => 'Maximum',
            'submission_count' => 'Submitted',
            'return_count'     => 'Returned',
        );

        $page->setVar('work_header_block', '');

        // transpose the data to output by row, not column, and output row headers while we're here
        $transposed = array();

        foreach ($this->statisticDataSet as $isOA => $workList) {
            foreach ($workList as $workId => $workData) {
                foreach ($workData as $statName => $statValue) {
                    // replace null (explicitly null) with an endash
                    $transposed[$statName]["$isOA-$workId"] = is_null($statValue) ? '&ndash;' : $statValue;
                }
            }
        }

        // iterate the transposed data and output
        foreach ($transposed as $name => $boxes) {
            $page->setVar('stat_item_block', '');

            // special case!
            if ($name == 'avg') {
                foreach ($this->displayOrder as $item) {
                    $workId = "{$item['isOA']}-{$item['id']}";
                    $value  = $boxes[$workId];

                    if (is_numeric($transposed['mean'][$workId])) {
                        $gradient = (int)($transposed['mean'][$workId] / 10);
                    } else {
                        $gradient = 'off';
                    }

                    $page->setVar(
                        array(
                            'WORK_CLASSAVG_GRADIENT' => $gradient,
                            'WORK_CLASSAVG_CLEAN'    => $value
                        )
                    );
                    $page->parse('class_average_block', 'class_average', true);
                }

                // don't do any of the other processing
                continue;
            }

            // not a special case, just process as normal

            $page->setVar(
                array(
                    'WORK_STAT_CLASS' => $name,
                    'WORK_STAT_TITLE' => isset($statLabelLookup[$name]) ? $statLabelLookup[$name] : ucfirst($name),
                )
            );

            $page->setVar('stat_item_block', '');
            foreach ($this->displayOrder as $item) {
                $workId = "{$item['isOA']}-{$item['id']}";
                $value  = $boxes[$workId];
                $page->setVar(
                    array(
                        'WORK_NS_ID'        => str_replace('-', '][', $workId),
                        // so it appears as the name attribute properly
                        'WORK_ID'           => $item['id'],
                        'WORK_STAT_VALUE'   => is_numeric($value) ? (float)round($value, 3) : $value,
                        // truncate to 3 decimals, then strip extraneous 0s
                        'ASSESS_MODIFY_URL' => $item['object'] instanceof OnlineAssessment ? '/assess/modify/' . $item['id'] : '/submission/submissionForm.php?id=' . $item['id'],
                    )
                );

                if ($name == 'weight') {
                    $page->parse('stat_editable_block', 'stat_editable');
                    $page->setVar('stat_visible_block', '');
                    $page->setVar('stat_readonly_block', '');
                } elseif ($name == 'visibility') {
                    $page->setVar('stat_visibility_drop_block', '');
                    foreach (Widgets::getAvailableOptions() as $option) {
                        if ($option['id'] == $transposed[$name][$workId]) {
                            $selected = 'selected';
                        }
                        else {
                            $selected = '';
                        }

                        $page->setVar(array(
                            'MARK_VISIBILITY_VALUE' => \Scrub::text($option['id']),
                            'MARK_VISIBILITY_TEXT' => \Scrub::text($option['name']),
                            'MARK_VISIBILITY_SELECTED' => $selected,
                        ));

                        $page->parse('stat_visibility_drop_block', 'stat_visibility_drop', true);
                    }
                    $page->parse('stat_visible_block', 'stat_visible');
                    $page->setVar('stat_editable_block', '');
                    $page->setVar('stat_readonly_block', '');
                } else {
                    $page->parse('stat_readonly_block', 'stat_readonly');
                    $page->setVar('stat_editable_block', '');
                    $page->setVar('stat_visible_block', '');
                }

                $page->parse('stat_item_block', 'stat_item', true);
            }
            $page->parse('stat_block', 'stat', true);
        }
        if (!count($transposed)) {
            $page->setVar('stat_block', '');
            $page->setVar('class_average_block', '');
        }


        // iterate student list and output rows
        $i = 0;

        foreach ($this->studentDataSet as $userId => $row) {
            /** @var $student User */
            $student = $row['student'];

            if (is_null($row['avg'])) {
                $gradient = 'off';
                $average  = '&ndash;';
            } else {
                $gradient = (int)($row['avg'] / 10);
                // This letter grade is an average of grades for this class,
                // and therefore doesn't have an actual submission box.
                $average = $this->marktypeFactoryHandler->build(
                    'letter',
                    new SubmissionBox()
                )->convertRawMark($row['avg'], null, true, false);
            }


            $page->setVar(
                array(
                    'STUDENT_NAME_FORMAL'  => $student->formatName('{lastname}, {firstname}'),
                    'STUDENT_NAME'         => $student->formatName(),
                    'STUDENT_EXTID'        => $student->getExternalId(),
                    'STUDENT_ID'           => $userId,
                    'DARK'                 => ++$i % 2 ? 'dark' : '',
                    'STUDENT_AVG_GRADIENT' => $gradient,
                    'STUDENT_AVG_CLEAN'    => $average

                )
            );

            $page->setVar('student_work_block', '');

            // iterate the ORDER array, so the columns come out in the correct sequence
            foreach ($this->displayOrder as $item) {
                // also, if this is the first pass, render the column headers as well
                if ($i == 1) {
                    if ($item['object'] instanceof OnlineAssessment) {
                        $page->setVar(
                            array(
                                'WORK_TITLE' => $item['object']->getTitle(),
                                'WORK_ID'    => $item['object']->getId(),
                                'WORK_PATH'  => '/assess/results',
                                'WORK_TYPE'  => 'Quiz'
                            )
                        );
                    } elseif ($item['object'] instanceof SubmissionBox) {
                        $page->setVar(
                            array(
                                'WORK_TITLE' => $item['object']->getName(),
                                'WORK_ID'    => $item['object']->getId(),
                                'WORK_PATH'  => '/submission/viewSubmission',
                                'WORK_TYPE'  => $item['object']->getWorkType()->getName()
                            )
                        );
                    }
                    $page->parse('work_header_block', 'work_header', true);
                }
                $mark   = $row['marks'][$item['isOA']][$item['id']];
                $workId = $item['id'];

                $markLabel = '';
                if ($mark['in_progress']) {
                    $markLabel = 'In Progress';
                } elseif (strlen($mark['mark'])) {
                    $markLabel = $mark['mark'];
                } else {
                    $markLabel = '&ndash;';
                }
                $page->setVar(
                    array(
                        'WORK_TYPE'              => $item['isOA'] ? 'assess' : 'submission',
                        'WORK_ID'                => $item['id'],
                        'STUDENT_WORK_NORM_MARK' => $mark['norm_mark'],
                        'STUDENT_WORK_MARK'      => $markLabel,
                        'STUDENT_WORK_COMMENT'   => isset($mark['comment']) ? $mark['comment'] : '',
                    )
                );

                if ($item['isOA']) {
                    // yes, you actually have to do this...
                    $section        = $this->sectionHandler->getByField('assess_id', $workId);
                    $firstSectionId = $section['section_id'];
                    $attempt        = $this->attemptHandler->getLatestForUser($workId, $student->getId());
                    $attemptId      = $attempt['attempt_id'];
                    if ($firstSectionId && $attemptId) {
                        $page->setVar('STUDENT_WORK_PATH', "/assess/review/$workId/$firstSectionId/$attemptId");
                    } else {
                        $page->setVar('STUDENT_WORK_PATH', '#');
                    }
                } else {
                    $page->setVar('STUDENT_WORK_PATH', "/submission/submissionHistory/$workId/$userId");
                }

                $page->parse('student_work_block', 'student_work', true);

            }
            if (!count($this->displayOrder)) {
                $page->setVar('work_header_block', '');
                $page->setVar('student_work_block', '');
            }

            $page->parse('student_row_block', 'student_row', true);
        }
        if (!count($this->studentDataSet)) {
            $page->setVar('student_row_block', '');
        }


    }

    /**
     * Render a CSV and force download
     */
    public function renderCsv()
    {

        /* Open an output stream so we can use fputcsv
          */
        $filename = preg_replace('/[^0-9a-zA-Z()_-]/', '_', $this->category->getName());

        header("Pragma: ");
        header("Cache-control: ");
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $filename . ".csv\"");

        $fp = fopen('php://output', 'w');

        // First level heading:
        $header    = array('External ID', 'Surname', 'Firstname');
        $subheader = array('', '', '');
        foreach ($this->displayOrder as $item) {
            if ($item['object'] instanceof OnlineAssessment) {
                // Online assess has only two columns
                $header[]    = $item['object']->getTitle();
                $header[]    = '';
                $subheader[] = 'Grade';
                $subheader[] = 'Total';
            } elseif ($item['object'] instanceof SubmissionBox) {
                $header[]    = $item['object']->getName();
                $header[]    = '';
                $header[]    = '';
                $header[]    = '';
                $subheader[] = 'Normalised Grade';
                $subheader[] = 'Grade';
                $subheader[] = 'Total';
                $subheader[] = 'Comment';
            }
        }
        fputcsv($fp, $header);
        fputcsv($fp, $subheader);

        foreach ($this->studentDataSet as $row) {
            /** @var User $student */
            $student = $row['student'];
            $output  = array(
                $student->getExternalId(),
                $student->getLastname(),
                $student->getFirstname(),
            );
            foreach ($this->displayOrder as $item) {
                $mark       = $row['marks'][$item['isOA']][$item['id']];
                $actualMark = '';
                $totalMark  = '';

                /* Total available marks in achieved/available format
                 */
                if (strstr($mark['mark'], ' / ')) {
                    list($actualMark, $totalMark) = explode(' / ', $mark['mark']);
                } else {
                    /* A mark value is provided
                     */
                    if ($mark['mark'] != '') {
                        $actualMark = $mark['mark'];

                        /* Quiz doesn't have getMaxMark() and are represented as
                         * achieved/available anyway
                         */
                        if ($item['object'] instanceof SubmissionBox) {
                            $totalMark = $item['object']->getMaxMark();
                        }
                    }
                }

                if ($item['object'] instanceof OnlineAssessment) {
                    $output[] = $actualMark;
                    $output[] = $totalMark;
                } elseif ($item['object'] instanceof SubmissionBox) {
                    $output[] = $mark['norm_mark'];
                    $output[] = $actualMark;
                    $output[] = $totalMark;
                    $output[] = $mark['comment'];
                }
            }
            fputcsv($fp, $output);
        }
        fclose($fp);
    }
}
