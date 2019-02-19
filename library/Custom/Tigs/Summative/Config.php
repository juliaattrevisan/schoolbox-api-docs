<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative;

/**
 * Catalogue of config for this custom module
 */
class Config
{

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
    public const _TIGS_PASTORAL_TAG = self::PREFIX_TIGS_CRP . 'pastoral_tag';
}
