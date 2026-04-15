<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy;

interface IStrategy
{
    /**
     * Used to make logging a bit more bearable
     */
    public const string WARNING = 'warning';
    public const string INFO = 'info';


    /**
     * Returns a list of expected columns unique to the due work import template
     *
     * @return array|string[]
     */
    public function getExpectedColumns(): array;


    /**
     * The query used to find assessments quickly
     *
     * @return string
     */
    public function getAssessmentQuery(): string;


    /**
     * Returns a list of error messages encountered during processing rows
     *
     * @return array
     */
    public function getMessages(): array;


    /**
     * Returns the number of errors we found during processing rows
     *
     * @return int
     */
    public function getErrorCount(): int;


    /**
     * Returns a list of convenient links to status screens
     *
     * @return array
     */
    public function getLinks(): array;
}
