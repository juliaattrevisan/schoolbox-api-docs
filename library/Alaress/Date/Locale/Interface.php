<?php
/**
 * Alaress_Date_Locale_Interface
 *
 * This defines universal methods which all localisations must use
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

interface Alaress_Date_Locale_Interface
{
    public function localShortFormat(): string;

    public function longFormat(): string;

    public function shortFormat(): string;

    public function dateOnlyFormat(): string;

    public function timeOnlyFormat(): string;

    public function localFormat(): string;

    public function longDateOnlyFormat(): string;

    public function shortDateOnlyFormat(): string;

    public function calendarDate(): string;

    public function calendarDateTime(): string;

    public function weekendDays(): array;
}
