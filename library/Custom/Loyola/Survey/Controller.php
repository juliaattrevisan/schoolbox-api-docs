<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Loyola\Survey;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use External_Class;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\UserMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Scrub;

class Controller extends Base
{
    /** @var UserMapper */
    private $userMapper;

    /** @var SurveyMapper */
    private $surveyMapper;

    /** @var Spreadsheet $excel */
    private $excel;

    /** @var Worksheet $activeSheet */
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
     * @param UserMapper               $userMapper
     * @param SurveyMapper             $surveyMapper
     * @param \External_ClassInterface $externalClass
     */
    public function __construct(
        UserMapper $userMapper,
        SurveyMapper $surveyMapper,
        \External_ClassInterface $externalClass
    ) {
        $this->userMapper    = $userMapper;
        $this->externalClass = $externalClass;
        $this->surveyMapper  = $surveyMapper;
    }

    /**
     * Display a list of all surveys the students in the chosen folder have completed and link to the
     * classroom learning snapshot
     *
     * @param Folder $folder
     */
    public function getClassSnapshotList(Folder $folder): void
    {
        $userIds = $this->getStudents($folder);
        // And work out what surveys they have completed
        $surveys = null;
        if(count($userIds) > 0) {
            $surveys = $this->surveyMapper->findAttemptedSurveysForUsers($userIds, 'backpack');
        }

        $this->getResponse()->content->set([
            'template' => '@surveySnapshot/classSnapshot.twig',
            'data' => [
                'folder' => $folder,
                'surveys' => $surveys
            ]
        ]);
    }




    /**
     * Retrieve spreadsheet containing aggregated survey results
     *
     * @param Survey $survey
     */
    public function getSnapshot(Survey $survey): void
    {
        $responses = $this->surveyMapper->findClassSnapshot($survey->getId());
        $this->renderSpreadsheet($survey, $responses);
    }

    /**
     * Retrieve spreadsheet containing aggregated survey results filtered to a class list
     *
     * @param Survey $survey
     * @param Folder $folder
     */
    public function getClassSnapshot(Survey $survey, Folder $folder): void
    {
        $userIds = $this->getStudents($folder);
        $responses = $this->surveyMapper->findClassSnapshot($survey->getId(), $userIds);
        $this->renderSpreadsheet($survey, $responses);
    }


    /**
     * Output a spreadsheet
     *
     * @param Survey $survey
     * @param array  $responses
     */
    private function renderSpreadsheet(Survey $survey, $responses): void
    {

        $this->excel = new Spreadsheet();
        $answersRenderArray = [];


        foreach ($responses as $response) {
            // Strip the section title of tags and create an array entry for it if none already exists
            $sectionTitle = Scrub::html2text($response['section_title']);

            if (!isset($answersRenderArray[$sectionTitle])) {
                $answersRenderArray[$sectionTitle] = [];
            }

            // Unserialize the available answers (into an array of objects) and then mash into an indexed array so
            // we can easily lookup the persons selected answer

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

                $answersRenderArray[$sectionTitle][Scrub::html2text($response['question'])][$response['user_id']] = $answers;
            }
        }

        // Throw this data into the spreadsheet

        foreach ($answersRenderArray as $tabText => $question) {
            // Create a tab for each section
            $this->spreadsheetTabSetup(
                $this->replaceInvalidCharacters($tabText),
                $this->replaceInvalidCharacters($survey->getFolder()->getNameWithCodes() . " - " . $survey->getTitle())
            );

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
                $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setIndent(1);
                $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setWrapText(true);

                $this->activeSheet->setCellValueByColumnAndRow($currentQuestionColumn, $this->currentRow, $questionText);

                $this->currentRow++;

                foreach ($users as $userId => $answers) {
                    // Draw in the respondents names
                    $user = $this->userMapper->findByIdWithFallback($userId);

                    $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getFont()->setBold(true);
                    $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
                    $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
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

                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setIndent(1);
                    $this->activeSheet->getStyleByColumnAndRow($currentQuestionColumn, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
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
    private function spreadsheetTabSetup($tabText, $title): void
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
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Classroom Learning Snapshot");
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getFont()->setBold(true);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, $title);
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow += 2;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Prepared By: " . $this->getUser()->getFullName());
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;

        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->activeSheet->getStyleByColumnAndRow(0, $this->currentRow)->getAlignment()->setIndent(1);
        $this->activeSheet->setCellValueByColumnAndRow(0, $this->currentRow, "Date/Time: " . Alaress_Date_Format::now()->getShort());
        $this->activeSheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow += 2;
    }

    /**
     * Download spreadsheet
     *
     * @param string $filename
     */
    private function spreadsheetDownload($filename): void
    {
        $tempFilename = tempnam(sys_get_temp_dir(), 'cls_');

        $excelWriter = new Xlsx($this->excel);
        $excelWriter->save($tempFilename);

        header('Content-Type: application/ms-excel');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        readfile($tempFilename);

        unlink($tempFilename);
    }

    /**
     * Replaces disallowed characters from tab names/titles with semi-similar ones that are allowed
     *
     * @param string $string
     * @return string
     */
    private function replaceInvalidCharacters($string): string
    {
        $replacements = [
            '\\' => '|',
            '/' => '|',
            '*' => '-',
            '[' => '(',
            ']' => ')',
            ':' => '-',
            '?' => ''
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $string
        );
    }


    /**
     * @param Folder $folder
     * @return array
     */
    private function getStudents(Folder $folder) : array
    {
        $students = [];

        // Get the class codes on the folder we are viewing
        $codes = $folder->getCodes()->getPropertyValues('code');

        // Retrieve the students in each of these classes
        foreach ($codes as $code) {
            $students = array_merge($students, $this->externalClass->getStudents($code));
        }

        // If we didn't find any students, no point going on
        if (count($students) === 0) {
            return [];
        }

        // Now convert those external user arrays into Schoolbox users
        $users = $this->userMapper->findByColumn(
            'synergy_id',
            array_column($students, 'synergy_id')
        );

        return $users->getPropertyValues('id');
    }
}
