<?php

/**
 * Error messages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Error;

use Alaress\Web\Controller\IExceptionGenerator;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper implements IExceptionGenerator
{
    // Error codes- these match with the corresponding error messages in `error` table.

    const ERROR_PERM_NOT_LOGGED_IN = 1;
    const ERROR_INFORMATION_MISSING = 2;
    const ERROR_FILE_OR_URL = 3;
    const ERROR_EMAIL_NOT_RETRIEVED = 4;
    const ERROR_PERM_DOWNLOAD_FILE = 5;
    const ERROR_PERM_CATEGORY_WRITE = 6;
    const ERROR_EMAIL_SEND = 7;
    const ERROR_PERM_RESOURCE_DELETE = 8;
    const ERROR_PROCESSING = 9;
    const ERROR_FILE_SIZE = 10;
    const ERROR_FILE_TYPE = 11;
    const ERROR_PERM_CATEGORY_DELETE_SUBFOLDERS = 12;
    const ERROR_CATEGORY_DELETED = 13;

    const ERROR_INVALID_DATE = 15;
    const ERROR_ACCOUNT_VALIDATION = 16;
    const ERROR_PERM_SUBMISSION = 17;
    const ERROR_SUBJECT_DELETED = 18;
    const ERROR_PERM_PAGE_VIEW = 19;
    const ERROR_PERM_CATEGORY_CREATE = 20;
    const ERROR_DATABASE = 21;
    const ERROR_DATABASE_UNAVAILABLE = 22;
    const ERROR_PERM_NEWS_MODIFY = 23;
    const ERROR_SUBJECT_NOT_ENROLLED = 24;
    const ERROR_PERM_WORK_RETURN = 25;
    const ERROR_WORK_DELETED = 26;
    const ERROR_WORK_SUBMITTED = 27;
    const ERROR_PERM_CATEGORY_POST = 28;
    const ERROR_PERM_POST_MODIFY = 29;
    const ERROR_PERM_GROUP_ADMIN = 30;
    const ERROR_USER_NOT_FOUND = 31;
    const ERROR_ABSENTEE_WEEKEND = 32;
    const ERROR_PERIOD_NOT_FOUND = 33;
    const ERROR_PERM_EVENT_MODIFY = 34;
    const ERROR_PERM_EVENT_ADD = 35;
    const ERROR_SUBMISSION_CLOSED = 36;
    const ERROR_PERM_GROUP_RESTRICTED = 37;
    const ERROR_FILE_DUPLICATE = 38;
    const ERROR_NO_TEAMS_FOUND = 39;
    const ERROR_ATTENDANCES_NOT_IMPLEMENTED = 40;
    const ERROR_FILE_NOT_FOUND = 41;
    const ERROR_EMAIL_NOT_FOUND = 42;
    const ERROR_FILE_UPLOAD_INCOMPLETE = 43;
    const ERROR_PERM_WORK_VIEW = 44;
    const ERROR_SEARCH_LENGTH = 45;
    const ERROR_EMAIL_VIEW_NOT_EXIST = 46;
    const ERROR_PERM_FILE_MODIFY = 47;
    const ERROR_PERM_FILE_DELETE = 48;
    const ERROR_ZIP_FOLDER_EMPTY = 49;
    const ERROR_PERM_FILE_DELETE_2 = 50;                // DIRECT DUPLICATE FOR LAWNMOW
    const ERROR_SECTION_NOT_FOUND = 51;
    const ERROR_EPORTFOLIO_LINK_ALREADY_SHARED = 52;
    const ERROR_EPORTFOLIO_DISABLED = 100;
    const ERROR_RESOURCE_NOT_FOUND = 101;
    const ERROR_BOOKING_TIME_TAKEN = 102;
    const ERROR_BOOKING_OUT_OF_RANGE = 103;
    const ERROR_PERM_BOOKING_MODIFY = 104;
    const ERROR_FORM_SUBMIT = 105;
    const ERROR_PERM_LINK = 106;
    const ERROR_PERM_BLOG_MODIFY = 107;
    const ERROR_PERM_BLOG_COMMENT_CREATE = 108;
    const ERROR_PERM_FILE_PUBLISH = 109;
    const ERROR_PERM_NOTES_MODIFY = 110;
    const ERROR_BLOG_NOT_FOUND = 111;
    const ERROR_QUIZ_NOT_FOUND = 112;
    const ERROR_INCOMPLETE_QUESTIONS = 113;
    const ERROR_NO_ATTEMPT_SELECTED = 114;
    const ERROR_SECTION_NOT_FOUND_2 = 115;          // KINDA DUPLICATED
    const ERROR_QUESTION_TYPE_UNSPECIFIED = 116;
    const ERROR_SELECT_QUIZ_GROUP = 117;
    const ERROR_ATTEMPT_NOT_FOUND = 118;
    const ERROR_QUESTION_NOT_FOUND = 119;
    const ERROR_QUIZ_EXPIRED = 120;
    const ERROR_QUIZ_ALREADY_ATTEMPTED = 121;
    const ERROR_QUIZ_TIME_COMPLETED = 122;
    const ERROR_ATTEMPT_NOT_RECORDED = 123;
    const ERROR_PERM_TEST_OWNER = 124;
    const ERROR_PERM_PAGE_VIEW_2 = 125;             // DIRECT DUPLICATE FOR LAWNMOW
    const ERROR_PERM_TEST_MODIFY = 126;
    const ERROR_PERM_TEST_REVIEW = 127;
    const ERROR_ATTEMPT_NOT_FOUND_2 = 128;
    const ERROR_PERM_TEST_VIEW = 129;
    const ERROR_PERM_TEST_MODIFY_2 = 130;           // KINDA DUPLICATED
    const ERROR_QUESTION_NOT_SAVED = 131;
    const ERROR_ANSWER_NOT_SAVED = 132;
    const ERROR_QUESTION_TYPE_NOT_FOUND = 133;
    const ERROR_IMPORT_FILE_NOT_FOUND = 134;
    const ERROR_INVALID_QUESTION_XML = 135;
    const ERROR_CLASS_CODE_EXISTS = 136;
    const ERROR_QUIZ_NO_QUESTIONS = 137;
    const ERROR_USERNAME_EXISTS = 138;
    const ERROR_LINK_FORMAT = 139;
    const ERROR_LINK_DUPLICATE = 140;
    const ERROR_WEBDAV = 141;
    const ERROR_PERM_ATTEMPT_DELETE = 142;
    const ERROR_QUIZ_NOT_OPEN = 143;
    const ERROR_SURVEY_NOT_OPEN = 144;
    const ERROR_START_TIME_GREATER = 145;
    const ERROR_MAXIMUM_ATTEMPTS = 146;
    const ERROR_CLASS_CODE_DATE_PERIOD = 147;
    const ERROR_CLASS_CODE_NOT_FOUND = 148;
    const ERROR_DATE_FORMAT = 149;
    const ERROR_PERIOD_NUMBER_INVALID = 150;
    const ERROR_ATTENDANCE_PAST = 151;
    const ERROR_ATTENDANCE_FUTURE = 152;
    const ERROR_NOT_ENROLLED = 153;
    const ERROR_PERM_CATEGORIES_WRITE = 154;
    const ERROR_PERM_PARENT_DIRECTORY_YEAR_LEVEL = 155;
    const ERROR_PAGE_NOT_FOUND = 156;
    const ERROR_SOCIAL_STREAM_MESSAGE = 157;
    const ERROR_SELECT_CAMPUS = 158;
    const ERROR_SUBJECT_NO_TASKS = 159;
    const ERROR_LEAVE_REQUEST_TOO_SOON = 162;
    const ERROR_EVENT_NOT_FOUND = 160;
    const ERROR_RAMS_FILE = 161;
    const ERROR_EVENT_STATUS_UNKNOWN = 163;
    const ERROR_COLLAB_DOC_NOT_FOUND = 164;
    const ERROR_EOTC_EVENT_NO_STUDENTS = 165;
    const ERROR_QUESTION_BANK_CATEGORY = 166;
    const ERROR_SURVEY_NOT_FOUND = 167;
    const ERROR_PASTORAL_PERM_RECORD = 168;
    const ERROR_PASTORAL_PERM_MODULE = 169;
    const ERROR_PASTORAL_USER_NOT_FOUND = 170;
    const ERROR_PASTORAL_NOT_FOUND = 171;
    const ERROR_COURSE_FOLDER_INVALID = 172;
    const ERROR_POST_NOT_FOUND = 173;

    const ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND = 174;
    const ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND = 175;
    const ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND = 176;
    const ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND = 177;
    const ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND = 178;
    const ERROR_SPORTSFIXTURES_POSITION_NOT_FOUND = 179;
    const ERROR_SPORTSFIXTURES_STATUS_NOT_FOUND = 181;
    const ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND = 182;
    const ERROR_SPORTSFIXTURES_SEASON_ARCHIVED = 183;

    const ERROR_GENERIC_NOT_FOUND = 180;

    const ERROR_ASSESSMENT_NO_ONLINE_RESPONSE = 184;
    const ERROR_ASSESSMENT_NO_MULTIPLE_RESPONSE = 185;
    const ERROR_ASSESSMENT_CLOSED = 186;
    const ERROR_ASSESSMENT_NOT_YET_OPEN = 187;

    const ERROR_ZIP_EXTRACT = 188;
    const ERROR_NO_ANALYTICS_DATA = 198;

    const ERROR_INVALID_TASK_COMPLETION = 199;

    const ERROR_INVALID_PD_DETAILS = 200;

    const ERROR_NO_SELF_ASSESS = 201;
    const ERROR_COMPLETE_BEFORE_SELF_REFLECT = 202;

    const ERROR_GROUP_NIAN_DENIED = 203;

    const ERROR_ATTENDANCE_SEQUENCE = 204;

    const ERROR_ASSESSMENT_DELETED = 205;
    const ERROR_FOLDER_DELETED = 206;
    public const ERROR_NO_COURSE_SPEC_INTERACTIONS = 207;
    public const ERROR_CODE_TOO_LONG = 208;
    public const ERROR_CAMPUS_CODE = 209;
    public const ERROR_ATTENDANCE_LIST = 210;
    public const ERROR_ATTENDANCE_SAVE = 211;
    public const ERROR_NO_SSO_IMPERSONATION = 212;

    public const ERROR_WIKI_NAME_IN_USE = 213;

    public const ERROR_SUBMISSION_WINDOW_EXCEEDED = 214;
    public const ERROR_TASS_SSO_FAILURE = 215;

    /* Unknown topic chosen
     */
    public const ERROR_COMMS_NEWS_INVALID_TOPIC = 224;

    public const ERROR_DISCUSSION_THREAD_ALREADY_ASSOCIATED = 225;

    public const ERROR_FORUM_FIRST_POST_CANNOT_BE_DELETED = 226;

    public const ERROR_EVENT_ATTENDANCE_MAX_REACHED = 227;
    public const ERROR_CALENDAR_IMPORT_FILE_REQUIRED = 228;

    public const ERROR_BADGE_AWARD_TO_NON_STUDENT_USER = 229;

    /* Geosnapshot
     */
    public const ERROR_GEOSNAPSHOT_UNAVAILABLE = 230;

    /* Clipboard
     */
    public const ERROR_CLIPBOARD_UNAVAILABLE = 231;

    /**
     * Setup mapping
     */
    public function mapping()
    {
        $this->hasTable('error');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('message', self::TYPE_STRING);
    }

    /**
     * @param int|null        $errorCode
     * @param \Throwable|null $previous
     * @param string          $additionalInfo
     * @param int             $httpStatusCode
     * @return SchoolboxException
     */
    public function generateException(
        ?int $errorCode,
        \Throwable $previous = null,
        string $additionalInfo = '',
        int $httpStatusCode = 400
    ): \Exception {
        if (0 === $errorCode || null === $errorCode) {
            $msg = $additionalInfo;
        } else {
            $msg = str_replace("%MESSAGE%", $additionalInfo, $this->getMessageById($errorCode));
        }

        return new SchoolboxException($msg, $errorCode, $previous, $httpStatusCode);
    }

    /**
     * Return error messages
     *
     * @param Int $id
     * @return string
     */
    public function getMessageById($id)
    {
        return $this->findById($id)->getMessage();
    }
}
