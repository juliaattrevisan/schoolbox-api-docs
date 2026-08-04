<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\ParseException;
use SimpleXMLElement;

/**
 * Parses SOCS XML calendar feeds into normalized event value objects.
 */
class Parser
{
    private DateTimeZone $timezone;

    /**
     * Capture the current instance timezone for SOCS date/time parsing.
     */
    public function __construct()
    {
        $this->timezone = new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Parse all calendar event nodes from a SOCSCalendar XML document.
     *
     * Feed-level errors remain fatal, while a callback lets callers isolate malformed
     * individual events without losing valid events from the same response.
     *
     * @param callable(ParseException): void|null $onEventMappingFailure
     * @return Event[]
     * @throws ParseException
     */
    public function parse(SimpleXMLElement $xml, ?callable $onEventMappingFailure = null): array
    {
        if (strcasecmp($xml->getName(), 'SOCSCalendar') !== 0) {
            throw new ParseException('SOCS calendar XML feed must use SOCSCalendar as the root element');
        }

        $events = [];
        foreach ($xml->xpath('//*[local-name()="CalendarEvent"]') ?: [] as $index => $eventNode) {
            try {
                $events[] = $this->buildEvent($eventNode, $index + 1);
            } catch (ParseException $e) {
                if (null === $onEventMappingFailure) {
                    throw $e;
                }
                $onEventMappingFailure($e);
            }
        }

        return $events;
    }

    /**
     * Build one event from a CalendarEvent XML node.
     *
     * @throws ParseException
     */
    private function buildEvent(SimpleXMLElement $eventNode, int $eventNumber): Event
    {
        $uid = $this->getChildValue($eventNode, ['UID', 'Uid', 'EventID', 'EventId', 'ID', 'Id', 'ModuleEventID']);
        if ($uid === '') {
            throw new ParseException("SOCS XML calendar event #{$eventNumber} is missing a UID/EventID field");
        }

        $title = $this->getChildValue($eventNode, ['Title', 'Summary', 'Name', 'Subject']);
        if ($title === '') {
            throw new ParseException("SOCS XML calendar event {$uid} is missing a Title/Summary field");
        }

        $startDate = $this->getChildValue($eventNode, ['StartDate', 'Date', 'Start']);
        if ($startDate === '') {
            throw new ParseException("SOCS XML calendar event {$uid} is missing a StartDate field");
        }

        $startTime = $this->normalizeTime($this->getChildValue($eventNode, ['StartTime']));
        $endDate = $this->getChildValue($eventNode, ['EndDate', 'FinishDate', 'End']) ?: $startDate;
        $endTime = $this->normalizeTime($this->getChildValue($eventNode, ['EndTime', 'FinishTime']));

        $start = $this->parseDateTime($startDate, $startTime, "start date for SOCS XML calendar event {$uid}");
        $end = $endTime === '' && $startTime !== ''
            ? $start
            : $this->parseDateTime($endDate, $endTime, "end date for SOCS XML calendar event {$uid}");
        if ($end < $start) {
            throw new ParseException("SOCS XML calendar event {$uid} has an end date before its start date");
        }

        return new Event(
            $uid,
            $title,
            $start,
            $end,
            $startTime === '',
            $this->getChildValue($eventNode, ['Description', 'Details', 'Notes']),
            $this->getChildValue($eventNode, ['Location', 'Venue']),
            $this->getChildValue($eventNode, ['Category', 'Categories']),
            $this->getSchools($eventNode),
        );
    }

    /**
     * Extract school/campus names from flat or nested SOCS school fields.
     *
     * @return string[]
     */
    private function getSchools(SimpleXMLElement $eventNode): array
    {
        $schools = [];
        foreach ($eventNode->children() as $childName => $child) {
            if (strcasecmp((string)$childName, 'Schools') !== 0 && strcasecmp((string)$childName, 'School') !== 0) {
                continue;
            }

            if ($child->children()->count() === 0) {
                array_push($schools, ...$this->splitSchools((string)$child));
                continue;
            }

            foreach ($child->children() as $school) {
                array_push($schools, ...$this->splitSchools((string)$school));
            }
        }

        return array_values(array_filter($schools, static fn (string $school): bool => $school !== ''));
    }

    /**
     * Parse a SOCS date and optional time in the configured instance timezone.
     *
     * @throws ParseException
     */
    private function parseDateTime(string $date, string $time, string $fieldDescription): DateTimeImmutable
    {
        $value = trim(trim($date) . ' ' . trim($time));
        $formats = trim($time) === ''
            ? ['!d/m/Y', '!Y-m-d', '!d-m-Y', '!m/d/Y']
            : ['!d/m/Y H:i', '!Y-m-d H:i', '!d-m-Y H:i', '!m/d/Y H:i', '!d/m/Y g:i A', '!m/d/Y g:i A'];

        foreach ($formats as $format) {
            $dateTime = DateTimeImmutable::createFromFormat($format, $value, $this->timezone);
            if ($dateTime instanceof DateTimeImmutable && $this->dateParsedCleanly()) {
                return $dateTime;
            }
        }

        throw new ParseException("Could not parse {$fieldDescription}: {$value}");
    }

    /**
     * Normalize SOCS' textual all-day marker to the parser's existing empty-time convention.
     *
     * This keeps all-day detection in one representation and avoids treating feed wording
     * as a clock value.
     *
     * @param string $time
     * @return string
     */
    private function normalizeTime(string $time): string
    {
        return 0 === strcasecmp($time, 'All Day') ? '' : $time;
    }

    /**
     * Confirm PHP parsed the previous date/time value without warnings or errors.
     */
    private function dateParsedCleanly(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    /**
     * Split a comma-separated SOCS schools string into clean school names.
     *
     * @return string[]
     */
    private function splitSchools(string $schools): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $schools)),
            static fn (string $school): bool => $school !== '',
        ));
    }

    /**
     * Return the first matching child value using a case-insensitive name comparison.
     *
     * @param string[] $names
     */
    private function getChildValue(SimpleXMLElement $node, array $names): string
    {
        foreach ($names as $name) {
            foreach ($node->children() as $childName => $child) {
                if (strcasecmp((string)$childName, $name) === 0) {
                    return trim((string)$child);
                }
            }
        }

        return '';
    }
}
