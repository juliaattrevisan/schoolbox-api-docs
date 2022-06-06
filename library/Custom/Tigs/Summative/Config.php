<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative;

/**
 * Catalogue of config for this custom module
 */
class Config
{

    // A data attribute to identify errors on the reports
    public const ERROR_DATA_ATTRIBUTE = 'data-tigs-crp-error-state';

    // All config for this module will share this common prefix
    public const PREFIX_TIGS_CRP = 'tigs_crp_';
    public const _TIGS_PARTICIPATION_REPORT_ENABLED = self::PREFIX_TIGS_CRP . 'participation_report_enabled';
    public const _TIGS_SUBJECT_REPORT_ENABLED = self::PREFIX_TIGS_CRP . 'subject_report_enabled';
    public const _TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX = self::PREFIX_TIGS_CRP . 'work_type_academic_achievement_matrix';
    public const _TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY = self::PREFIX_TIGS_CRP . 'work_type_academic_achievement_summary';
    public const _TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE = self::PREFIX_TIGS_CRP . 'work_type_diploma_programme_report_grade';
    public const _TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS = self::PREFIX_TIGS_CRP . 'work_type_myp_final_achievement_levels';
    public const _TIGS_WORK_TYPE_NESA_SCORE = self::PREFIX_TIGS_CRP . 'work_type_nesa_score';
    public const _TIGS_WORK_TYPE_HSC_ASSESSMENT_TASK = self::PREFIX_TIGS_CRP . 'work_type_hsc_assessment_task';
    public const _TIGS_WORK_TYPE_HSC_ASSESSMENT_RANK = self::PREFIX_TIGS_CRP . 'work_type_hsc_assessment_rank';
    public const _TEXTBOX_HEADING = self::PREFIX_TIGS_CRP . 'course_description_textbox_heading';
    public const _TIGS_PASTORAL_TYPE = self::PREFIX_TIGS_CRP . 'pastoral_type';
    public const _TIGS_GRADES_INFO_YEAR_7_10 = self::PREFIX_TIGS_CRP . 'grades_info_year_7-10';
    public const _TIGS_GRADES_INFO_YEAR_11_IB = self::PREFIX_TIGS_CRP . 'grades_info_year_11_ib';
    public const _TIGS_GRADES_INFO_YEAR_11_HSC = self::PREFIX_TIGS_CRP . 'grades_info_year_11_hsc';
    public const _TIGS_GRADES_INFO_YEAR_12_IB = self::PREFIX_TIGS_CRP . 'grades_info_year_12_ib';
    public const _TIGS_GRADES_INFO_YEAR_12_HSC = self::PREFIX_TIGS_CRP . 'grades_info_year_12_hsc';
    public const _TIGS_EXCLUDED_CLASS_CODES = self::PREFIX_TIGS_CRP . 'excluded_class_codes';
    public const _TIGS_START_TERM_1 = self::PREFIX_TIGS_CRP . 'start_term_1';
    public const _TIGS_START_TERM_2 = self::PREFIX_TIGS_CRP . 'start_term_2';
    public const _TIGS_START_TERM_3 = self::PREFIX_TIGS_CRP . 'start_term_3';
    public const _TIGS_START_TERM_4 = self::PREFIX_TIGS_CRP . 'start_term_4';
    public const _TIGS_TEXTBOX_HEADING_EXPLANATION_OF_GRADES = self::PREFIX_TIGS_CRP . 'textbox_heading_explanation_of_grades';
    public const _TIGS_WORK_TYPE_NESA_REPORT_GRADE_AND_REPORT_MARK = self::PREFIX_TIGS_CRP . 'work_type_nesa_report_grade_and_report_mark';
    public const _TIGS_WORK_TYPE_7_10_ASSESSMENT_TASK = self::PREFIX_TIGS_CRP . 'work_type_7_10_assessment_task';
    public const _TIGS_WORK_TYPE_DEANS_COMMENT = self::PREFIX_TIGS_CRP . 'work_type_deans_comment';
    public const _TIGS_YEAR_GROUP_CLASS_CODE_SUFFIX = self::PREFIX_TIGS_CRP . 'year_group_class_code_suffix';
}
