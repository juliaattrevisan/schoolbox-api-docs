<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Alaress\Web\Controller\Base as BaseController;
use DueWork_Rubric_Abstract;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Rubric\Descriptor as RubricDescriptor;
use Schoolbox\Collection;
use Schoolbox\User;

/**
 * FWIW, every protected method in here probably belongs in some other class, since they're all data manipulation
 *
 *
 * Class Controller
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox
 */
class Controller extends BaseController
{

    /**
     * Warning: handlers in use!
     *
     * @param SubmissionBox\Rubric\Rubric $rubric
     * @param Collection|User[]           $students
     * @return array
     */
    protected function getMarks($rubric, $students)
    {
        $marks = [];

        foreach ($students as $student) {
            $studentId = $student->getId();

            // this will modify the "selection" of descriptors in the grid
            SubmissionBox\Rubric\RubricMapper::getInstance()->applyStudentMark($rubric, $student);

            foreach ($rubric->getSumPerGroup() as $group) {
                foreach ($group->getAllCriteria() as $criterion) {
                    $marks[$studentId][$criterion->getId()] = $criterion->getSelectedDescriptors()->first();
                }
            }
        }

        return $marks;
    }


    /**
     * Warning: handlers in use!
     *
     * @param SubmissionBox $box
     * @return Collection|User[]
     */
    protected function getStudents(SubmissionBox $box)
    {
        $_DEPRECATED_SUBMISSION_HANDLER = new \Submission();

        // a handler...that accepts and returns mapped objects...
        return $_DEPRECATED_SUBMISSION_HANDLER->getAllParticipantsForSubmission($box);
    }


    /**
     * @param SubmissionBox $box
     * @return SubmissionBox\Rubric\Rubric
     */
    protected function getRubric(SubmissionBox $box)
    {
        return SubmissionBox\Rubric\RubricMapper::getInstance()->findBySubmissionBox($box);
    }


    /**
     * Warning: handlers in use!
     *
     * @param SubmissionBox $box
     * @return string
     */
    protected function getSubmissionBoxNameAsSlug(SubmissionBox $box)
    {
        $u = new \Url($box->getName());
        return $u->getSlug();
    }


    /**
     * @param SubmissionBox\Rubric\Rubric $rubric
     * @return array
     */
    protected function getRubricHeaders(SubmissionBox\Rubric\Rubric $rubric)
    {
        $headers  = [];

        foreach ($rubric->getTotalPerGroup() as $group) {
            foreach ($group->getAllCriteria() as $criterion) {
                $headers[$group->getName()][$criterion->getId()] = $criterion->getName();
            }
        }

        return $headers;
    }


    /**
     * @param SubmissionBox $box
     * @return array
     */
    protected function collateResultSquare(SubmissionBox $box)
    {
        $rubric   = $this->getRubric($box);
        $students = $this->getStudents($box);

        // array inside an array that's *nearly* empty...
        $data = [['Name', 'External ID']];

        // put header row in array
        foreach ($this->getRubricHeaders($rubric) as $grouping => $criteria) {
            foreach ($criteria as $id => $criterion) {
                $data[0][] = "$grouping - $criterion";
            }
        }

        /**
         * Collate by id, each sub-collection will contain only 1 User element
         *
         * @var Collection[] $studentsGrouped
         */
        $studentsGrouped = $students->groupBy('id')->getAll();

        foreach ($this->getMarks($rubric, $students) as $studentId => $marks) {
            /** @var User $student */
            $student     = $studentsGrouped[$studentId]->first();
            $studentName = $student->formatName('{lastname}, {firstname}');

            $row = [ $studentName, $student->getExternalId() ];
            if (count($marks)) {
                foreach ($marks as $mark) {
                    $field = $mark;
                    if ($mark instanceof RubricDescriptor) {
                        $value = $mark->getValue();
                        if (isset($value)) {
                            $field = sprintf('(%.02f) %s', $mark->getValue(), $mark->getText());
                        }
                    }
                    $row[] = $field;
                }
            } else {
                $row = array_merge($row, array_pad([], count($data[0]) - 1, ''));
            }
            $data[] = $row;
        }

        return $data;
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array)
    {
        $rows = [];

        foreach ($array as $row) {
            $rows[] = '"' . implode('","', $row) . '"';
        }

        return implode("\n", $rows);
    }


    /**
     * View the marks overview for the given box
     *
     * @api   /submission/overview/{box_id}
     * @level 150
     *
     * @param SubmissionBox $box
     */
    public function viewOverview(SubmissionBox $box)
    {
        $students = $this->getStudents($box);
        $rubric   = $this->getRubric($box);

        $data = [
            'box'      => $box,
            'students' => $students,
            'headers'  => $this->getRubricHeaders($rubric),
            'marks'    => $this->getMarks($rubric, $students)
        ];

        $this->getResponse()->content->set([
            'title'    => 'Overview',
            'template' => '@submission/overview.twig',
            'data'     => $data
        ]);
    }


    /**
     * Download the CSV of the marks overview for the given box
     *
     * @api   /submission/overview/{box_id}/csv
     * @level 150
     *
     * @param SubmissionBox $box
     */
    public function downloadOverview(SubmissionBox $box)
    {
        $basename = $this->getSubmissionBoxNameAsSlug($box);
        $content  = $this->array2csv($this->collateResultSquare($box));
        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($content));


        $headers->set('Content-Disposition', "attachment; filename=\"$basename-results.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }

} 
