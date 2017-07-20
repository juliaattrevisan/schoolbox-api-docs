<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use External_Class;
use PHPExcel;
use PHPExcel_Style_Alignment;
use PHPExcel_Worksheet;
use PHPExcel_Writer_Excel2007;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Scrub;

class Controller extends Base
{
    /** @var UserMapper */
    private $userMapper;

    /** @var SurveyMapper */
    private $surveyMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var PHPExcel $excel */
    private $excel;

    /** @var PHPExcel_Worksheet $activeSheet */
    private $activeSheet;

    /** @var int $tabIndex */
    private $tabIndex;

    /** @var int $currentRow */
    private $currentRow;

    /** @var External_Class $externalClass */
    private $externalClass;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param SurveyMapper $surveyMapper
     * @param FolderMapper $folderMapper
     */
    public function __construct(
        UserMapper $userMapper,
        FolderMapper $folderMapper,
        SurveyMapper $surveyMapper
    ) {
        $this->externalClass = new External_Class();
        $this->userMapper = $userMapper;
        $this->surveyMapper = $surveyMapper;
        $this->folderMapper = $folderMapper;
    }

    /**
     * Perform an AJAX search for surveys
     *
     * @return string
     */
    public function getSelectize()
    {
        return $this->getResponse()->content->set($this->buildJsonOutput(
            $this->surveyMapper->findByKeyword(
                $this->getRequest()->query['query']
            )
        ));
    }

    /**
     * Display a list of all surveys the students in the chosen folder have completed and link to the
     *
     * @param Folder $folder
     * @return bool
     */
    public function getClassSnapshotList(Folder $folder)
    {
        $students = [];
        $userIds = [];

        // Get the class codes on the folder we are viewing

        $codes = $folder->getCodes()->getColumnValues('code');

        // Retrieve the students in each of these classes

        foreach ($codes as $code) {
            $students = array_merge($students, $this->externalClass->getStudents($code));
        }

        // If we didn't find any students, no point going on

        if (count($students) == 0) {
            return false;
        }

        // Now convert those external user arrays into Schoolbox users

        $users = $this->userMapper->findByColumn(
            'synergy_id',
            array_column($students, 'synergy_id')
        );

        /** @var User $user */
        foreach ($users as $user) {
            $userIds[] = $user->getId();
        }

        // If we didn't find any schoolbox users, no point going on

        if (count($userIds) == 0) {
            return false;
        }

        // And work out what surveys they have completed

        $surveys = null;

        $surveys = $this->surveyMapper->findAttemptedSurveysForUsers($userIds);

        $this->getResponse()->content->set([
            'template' => '@survey/classSnapshot.twig',
            'data' => [
                'folder' => $folder,
                'surveys' => $surveys
            ]
        ]);

        return true;
    }

    /**
     * Retrieve spreadsheet containing aggregated survey results
     *
     * @param Model $survey
     */
    public function getClassSnapshot($survey)
    {
        $this->excel = new PHPExcel();

        if ($survey instanceof Model) {

            $answersRenderArray = [];

            $responses = Mapper::getInstance()->findClassSnapshot($survey->getId());

            foreach ($responses as $response) {

                // Strip the section title of tags and create an array entry for it if none already exists

                $sectionTitle = Scrub::html2text($response['section_title']);

                if (!isset($answersRenderArray[$sectionTitle])) {
                    $answersRenderArray[$sectionTitle] = [];
                }

                // Unserialize the available answers (into an array of objects) and then mash into an indexed array so we can easily lookup
                // the persons selected answer

                $answers = unserialize($response['answers']);

                $answersArray = [];

                if (is_array($answers)) {

                    foreach ($answers as $answer) {
                        $answersArray[$answer->value] = $answer->description;
                    }

                    $selectedAnswers = unserialize($response['selectedAnswers']);

                    $answers = [];

                    if (is_array($selectedAnswers)) {
                        foreach ($selectedAnswers as $answer) {
                            $answers[] = $answersArray[Scrub::html2text($answer)];
                        }
                    }

                    if (!isset($answersRenderArray[$sectionTitle][Scrub::html2text($response['question'])])) {
                        $answersRenderArray[$sectionTitle][Scrub::html2text($response['question'])] = [];
                    }

                    $answersRenderArray[$sectionTitle][Scrub::html2text($response['question'])][$response['user_id']]= $answers;
                }
            }

            // Throw this data into the spreadsheet

            foreach ($answersRenderArray as $tabText => $question) {

                // Create a tab for each section

                $this->spreadsheetTabSetup($tabText, $survey->getFolder()->getNameWithCodes() . " - " . $survey->getTitle());
                $this->activeSheet->getDefaultRowDimension()->setRowHeight(-1);

                // Start from the left

                $currentQuestionColumn = 1;

                $startRow = $this->currentRow;

                // Iterate each question

                foreach ($question as $questionText => $users) {

                    // Move back to the start row

                    $this->currentRow = $startRow;

                    $this->activeSheet->getColumnDimensionByColumn($currentQuestionColumn)->setAutoSize(true);

                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getFont()->setBold(true);
                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setIndent(1);
                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setWrapText(true);

                    $this->activeSheet->setCellValueByColumnAndRow($currentQuestionColumn, $this->currentRow, $questionText);

                    $this->currentRow++;

                    foreach ($users as $userId => $answers) {

                        // Draw in the respondents names

                        $user = $this->userMapper->findByIdWithFallback($userId);

                        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getFont()->setBold(true);
                        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
                        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
                        $name = $survey->getAnonymousResult()
                            ? ''
                            : "{$user->getLastname()}, {$user->getFirstname()}";
                        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, $name);

                        // Concatenate all the answers together into one cell

                        $answerText = "";

                        $rows = 0;
                        foreach ($answers as $answerTextCurrent) {
                            if ($answerText != "") {
                                $answerText .= "\n";
                            }
                            $answerText .= "• {$answerTextCurrent}";
                            $rows++;
                        }

                        $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setIndent(1);
                        $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
                        $this->activeSheet->setCellValueByColumnAndRow($currentQuestionColumn, $this->currentRow, $answerText);
                        $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setWrapText(true);

                        if ($this->activeSheet->getRowDimension($this->currentRow)->getRowHeight() < 16 * $rows) {
                            $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(16 * $rows);
                        }

                        // Move down to next row

                        $this->currentRow++;
                    }

                    $currentQuestionColumn++;
                }
            }
        }

        // Remove the default worksheet

        $this->excel->removeSheetByIndex(0);

        // Download the spreadsheet

        $this->spreadsheetDownload("cls-report-{$survey->getId()}-{$survey->getFolder()->getId()}.xlsx");
        exit;
    }

    /**
     * Setup the spreadsheet for the export
     *
     * @param string $tabText
     * @param string $title
     */
    private function spreadsheetTabSetup($tabText, $title)
    {
        $this->currentRow = 2;

        if (empty($this->tabIndex)) {
            $this->excel->getProperties()->setCreator($this->getUser()->getFullName());
            $this->tabIndex = 0;
        } else {
            $this->tabIndex++;
        }

        $this->activeSheet = $this->excel->createSheet();

        $this->activeSheet->setTitle($tabText);
        $this->activeSheet->getColumnDimensionByColumn(0)->setAutoSize(true);

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getFont()->setBold(true);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Classroom Learning Snapshot");
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getFont()->setBold(true);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, $title);
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow += 2;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Prepared By: " . $this->getUser()->getFullName());
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Date/Time: " . Alaress_Date_Format::now()->getShort());
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow += 2;

    }

    /**
     * Download spreadsheet
     *
     * @param $filename
     */
    private function spreadsheetDownload($filename)
    {
        $tempFilename = tempnam(sys_get_temp_dir(), 'cls_');

        $excelWriter = new PHPExcel_Writer_Excel2007($this->excel);
        $excelWriter->save($tempFilename);

        header('Content-Type: application/ms-excel');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        readfile($tempFilename);

        unlink($tempFilename);
    }
}
