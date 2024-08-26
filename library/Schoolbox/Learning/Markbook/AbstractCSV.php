<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\TotalMarkAbstract;
use Schoolbox\User;

/**
 * Abstract class to help exporting markbook to some format
 */
class AbstractCSV
{
    /**
     * A suffix for all fields in the CSV which are a draft value, or are
     * calculated from values which contain one or more drafts values.
     */
    protected const DRAFT_SUFFIX = ' (draft)';

    /**
     * A prefix for human-readable mark fields in the CSV, designed to prevent
     * date-like human-readable marks from being rendered as date values when
     * viewing in Excel.
     *
     * Yes, this is not a valid UTF-8 character: it still makes Excel behave
     * sort-of as expected.
     */
    protected const EXCEL_PREFIX = "\xA0";

    /**
     * @var resource
     */
    protected $fp;


    /** @var bool $viewRank */
    protected $viewRank;

    /**
     * constructor, to allow the passing through of the rank config bool
     * @param bool $viewRank
     */
    public function __construct(bool $viewRank)
    {
        $this->viewRank = $viewRank;
    }

    /**
     * Opens file to export
     *
     * @param string $filename
     */
    protected function initExport(string $filename): void
    {
        // set the output headers
//        header('Content-type: text/csv; charset=utf-8');
//        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $this->fp = fopen("php://output", 'w');
    }


    /**
     * Exports a row
     *
     * @param array $rowHeadings
     * @param array $rowData
     */
    protected function exportRow(array $rowHeadings, array $rowData): void
    {
        fputcsv($this->fp, array_merge($rowHeadings, $rowData));
    }


    /**
     * Closes exported file
     */
    protected function closeExport(): void
    {
        fclose($this->fp);
    }


    /**
     * Write CSV column headers
     *
     * @param GenericCollection|IAssessable[] $sortedAssessments
     */
    protected function writeHeader(GenericCollection $sortedAssessments): void
    {
        // Column headings
        $headings         = [
            'Student Id',
            'Student First Name',
            'Student Last Name',
            'Student Weighted Average',
        ];
        //is rank enabled
        if ($this->viewRank) {
            array_push($headings, 'Student Rank');
        }
        $assessmentTitles = [];
        foreach ($sortedAssessments as $assessment) {
            $assessmentTitles = $this->addHeaders($assessmentTitles, $assessment);
        }
        $this->exportRow($headings, $assessmentTitles);
    }


    /**
     * Append fields into header row for specified assessment
     *
     * @param string[]    $row
     * @param IAssessable $assessment
     * @return string[]
     */
    protected function addHeaders(array $row, IAssessable $assessment): array
    {
        $title = "{$assessment->getTitle()} \r\n({$assessment->getTypeName()})";

        if ($this->hasTotalMarkColumn($assessment)) {
            $row[] = "$title";
            $row[] = "$title (Total)";
        } else {
            $row[] = "$title";
        }
        if ($this->hasNormalizedMarkColumn($assessment)) {
            $row[] = "$title (Normalized)";
        }
        if ($this->hasCommentColumn($assessment)) {
            $row[] = "$title (Comment)";
        }
        return $row;
    }


    /**
     * Parse the denominator from a total mark
     *
     * @param null|string $humanReadableMark
     * @return null|string
     */
    protected function parseDenominator(?string $humanReadableMark): ?string
    {
        if ($humanReadableMark && preg_match('/(\\d*\\.?\\d*)\\s*\\/\\s*(\\d*\\.?\\d*)/', $humanReadableMark, $markParts)) {
            return $markParts[2];
        }
        return null;
    }


    /**
     * Parse the numerator from a total mark
     *
     * @param null|string $humanReadableMark
     * @return null|string
     */
    protected function parseNumerator(?string $humanReadableMark): ?string
    {
        if ($humanReadableMark && preg_match('/(\\d*\\.?\\d*)\\s*\\/\\s*(\\d*\\.?\\d*)/', $humanReadableMark, $markParts)) {
            return $markParts[1];
        }
        return null;
    }


    /**
     * Retrieves comment field value for this assessment and student.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return null|string
     */
    protected function retrieveCommentField(IAssessable $assessment, User $student): ?string
    {
        $participant = $assessment->getParticipantIfExtant($student);
        if (!$participant) {
            return '';
        }
        $mostRecentFeedback = $participant->findMostRecentInstructorGivenFeedback();
        return $mostRecentFeedback ? $mostRecentFeedback->getComment() : '';
    }


    /**
     * Does this assessment have a normalized mark column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasNormalizedMarkColumn(IAssessable $assessment): bool
    {
        return !($assessment->getMarkScheme() instanceof None || $assessment->getMarkScheme() instanceof Completion);
    }


    /**
     * Does this assessment have a total mark column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasTotalMarkColumn(IAssessable $assessment): bool
    {
        return $assessment->getMarkScheme() instanceof TotalMarkAbstract;
    }


    /**
     * Does this assessment have a comment column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasCommentColumn(IAssessable $assessment): bool
    {
        // Only DueWorks have comments at the moment
        return $assessment instanceof DueWork\Model;
    }

    /**
     * Extract a draft or published value from `$stats`.
     *
     * If `$showDrafts`: extract `$stats[$draftKey]`, and add a "draft" suffix:
     * otherwise, extract `$stats[$publishedKey]`.
     *
     *
     * @param array  $stats
     * @param bool   $showDrafts
     * @param string $publishedKey
     * @param string $draftKey
     * @return string
     */
    protected function extractDraftOrPublishedValue(
        array $stats,
        bool $showDrafts,
        string $publishedKey,
        string $draftKey,
    ): string {
        if ($showDrafts) {
            if (isset($stats[$draftKey])) {
                return $stats[$draftKey] . self::DRAFT_SUFFIX;
            }
        }
        return (string)($stats[$publishedKey] ?? '');
    }
}
