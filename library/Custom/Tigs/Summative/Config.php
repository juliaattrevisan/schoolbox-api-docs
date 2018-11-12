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
}
