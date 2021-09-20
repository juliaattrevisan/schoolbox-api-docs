<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;

/**
 * Decide if a Submission File fulfils the TurnItIn requirements
 * for size and mime type
 *
 * This applies when you're saving stubs and when you're trying to
 * process them for real
 */
class ValidFile
{
    /**
     * A list of extensions accepted by turnitin (they do their own mime check)
     *
     * You can find a list here:
     * https://help.turnitin.com/feedback-studio/canvas/lti/instructor/submitting-papers/file-types-and-size.htm#
     * https://help.turnitin.com/crossref-similarity-check/content-partner-portal-report.htm
     *
     * And because particularly nice, I've listed which app belongs each extension below
     */
    private const VALID_EXTENSIONS = [
        'html',     // generic html (plaintext)
        'rtf',      // rich text format
        'txt',      // generic text (plaintext)
        'wpd',      // Corel Wordperfect
        'hwp',      // Hancom Hangul Word
        'pdf',      // Adobe PDF
        'ps',       // Adobe PostScript
        'doc',      // Microsoft Word/Google Docs
        'docx',     // Microsoft Word/Google Docs
        'ppt',      // Microsoft Powerpoint/Google Docs
        'ppsx',     // Microsoft Powerpoint/Google Docs
        'pps',      // Microsoft Powerpoint/Google Docs
        'xls',      // Microsoft Excel/Google Docs
        'xlsx'      // Microsoft Excel/Google Docs
    ];


    /**
     * Extensions that would be classified as "Text only files"
     *
     * Shamefully they haven't stated whether they meant .txt specifically or
     * anything that could be opened and edited meaningfully using Notepad or Vim
     */
    private const TEXT_EXTENSIONS = [
        'html', 'rtf', 'txt'
    ];


    /**
     * Turnitin has a max file size of 100mb for most files
     * https://help.turnitin.com/feedback-studio/canvas/lti/instructor/submitting-papers/file-types-and-size.htm#
     */
    private const MAX_TURNITIN_FILESIZE_OTHER = 100 * 1024 * 1024;


    /**
     * But text files must be smaller than 2mb
     * https://help.turnitin.com/feedback-studio/canvas/lti/instructor/submitting-papers/file-types-and-size.htm#
     */
    private const MAX_TURNITIN_TEXT_FILESIZE_TEXT = 2 * 1024 * 1024;


    /**
     * There's a hidden requirement that text files have at least 50 chars
     * If it's too short you end up with error code TOO_LITTLE_TEXT
     */
    private const MIN_TURNITIN_TEXT_LENGTH = 50;


    /**
     * Each $file must pass the stringent whitelist checks here
     *
     * @param SubmissionFile $file
     * @return bool
     */
    public function isSatisfiedBy(SubmissionFile $file): bool
    {
        /* Must be one of the allowed filetypes; reject immediately if not allowed
         */
        if (!\in_array($file->getExtension(), self::VALID_EXTENSIONS)) {
            return false;
        }

        /* Text files must be less than the turnitin limit
         */
        if (\in_array($file->getExtension(), self::TEXT_EXTENSIONS, true)) {
            return $file->getSize() < self::MAX_TURNITIN_TEXT_FILESIZE_TEXT;
        }

        /* Other non-texty files must be less than the limit
         */
        if ($file->getSize() < self::MAX_TURNITIN_FILESIZE_OTHER) {
            return true;
        }

        /* Default case: deny validity
         */
        return false;
    }
}
