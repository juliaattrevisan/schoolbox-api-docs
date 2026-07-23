<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\ParseException;

/**
 * Parse SOCS co-curricular XML feeds into a complete normalized data set.
 */
final class Parser
{
    private DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Parse and validate the clubs, events, and registers feeds together.
     *
     * @return DataSet
     * @throws ParseException When a feed has an invalid root or mandatory value
     */
    public function parse(
        \SimpleXMLElement $clubsXml,
        \SimpleXMLElement $eventsXml,
        \SimpleXMLElement $registersXml,
    ): DataSet {
        $this->requireRoot($clubsXml, 'clubs');
        $this->requireRoot($eventsXml, 'events');
        $this->requireRoot($registersXml, 'registers');

        $clubs = $this->parseClubs($clubsXml);
        $events = $this->parseEvents($eventsXml);
        $pupilIdsByClubId = $this->parseRegisters($registersXml, $events);

        return new DataSet($clubs, $events, $pupilIdsByClubId);
    }

    /**
     * Confirm an XML document uses its expected root element.
     *
     * @throws ParseException
     */
    private function requireRoot(\SimpleXMLElement $xml, string $expected): void
    {
        if ($xml->getName() !== $expected) {
            throw new ParseException(sprintf(
                'SOCS co-curricular %s XML feed must use %s as the root element',
                $expected,
                $expected,
            ));
        }
    }

    /**
     * Parse unique clubs indexed by SOCS ClubID.
     *
     * @return array<string,Club>
     * @throws ParseException
     */
    private function parseClubs(\SimpleXMLElement $xml): array
    {
        $clubs = [];

        foreach ($xml->club as $index => $clubXml) {
            $number = ((int)$index) + 1;
            $id = $this->requireValue($clubXml, 'clubid', "SOCS co-curricular club #{$number}");
            $name = $this->requireValue($clubXml, 'clubname', "SOCS co-curricular club #{$number}");
            $category = $this->requireValue($clubXml, 'category', "SOCS co-curricular club #{$number}");

            $clubs[$id] = new Club($id, $name, $category);
        }

        return $clubs;
    }

    /**
     * Parse unique event occurrences indexed by SOCS EventID.
     *
     * @return array<string,Event>
     * @throws ParseException
     */
    private function parseEvents(\SimpleXMLElement $xml): array
    {
        $events = [];

        foreach ($xml->event as $index => $eventXml) {
            $number = ((int)$index) + 1;
            $context = "SOCS co-curricular event #{$number}";
            $id = $this->requireValue($eventXml, 'eventid', $context);
            $clubId = $this->requireValue($eventXml, 'clubid', "SOCS co-curricular event {$id}");
            $title = $this->requireValue($eventXml, 'title', "SOCS co-curricular event {$id}");
            $date = $this->requireValue($eventXml, 'startdate', "SOCS co-curricular event {$id}");
            $allDay = strtolower(trim((string)$eventXml->alldayevent)) === 'true';

            if ($allDay) {
                $start = $this->parseDateTime($date, '00:00:00', $id);
                $finish = $this->parseDateTime($date, '23:59:59', $id);
            } else {
                $startTime = $this->requireValue($eventXml, 'starttime', "SOCS co-curricular event {$id}");
                $endTime = $this->requireValue($eventXml, 'endtime', "SOCS co-curricular event {$id}");
                $start = $this->parseDateTime($date, $startTime, $id);
                $finish = $this->parseDateTime($date, $endTime, $id);
            }

            if ($finish < $start) {
                throw new ParseException(
                    "SOCS co-curricular event {$id} has an end time before its start time"
                );
            }

            $events[$id] = new Event(
                $id,
                $clubId,
                $title,
                trim((string)$eventXml->location),
                $start,
                $finish,
                $allDay,
                trim((string)$eventXml->recurringid),
            );
        }

        return $events;
    }

    /**
     * Union registered pupil IDs across every event belonging to each ClubID.
     *
     * SOCS registrations are occurrence-specific. This union intentionally supports
     * the Fixtures team-membership visibility model used by the importer.
     *
     * @param array<string,Event> $events
     * @return array<string,string[]>
     * @throws ParseException
     */
    private function parseRegisters(\SimpleXMLElement $xml, array $events): array
    {
        $pupilIdSetsByClubId = [];

        foreach ($xml->pupil as $index => $pupilXml) {
            $number = ((int)$index) + 1;
            $eventId = $this->requireValue(
                $pupilXml,
                'eventid',
                "SOCS co-curricular register #{$number}"
            );
            $pupilId = $this->requireValue(
                $pupilXml,
                'pupilid',
                "SOCS co-curricular register #{$number}"
            );

            if (!isset($events[$eventId])) {
                continue;
            }

            $pupilIdSetsByClubId[$events[$eventId]->clubId][$pupilId] = true;
        }

        $pupilIdsByClubId = [];
        foreach ($pupilIdSetsByClubId as $clubId => $pupilIdSet) {
            $pupilIdsByClubId[$clubId] = array_keys($pupilIdSet);
            sort($pupilIdsByClubId[$clubId]);
        }

        return $pupilIdsByClubId;
    }

    /**
     * Read one mandatory XML child value.
     *
     * @throws ParseException
     */
    private function requireValue(\SimpleXMLElement $xml, string $field, string $context): string
    {
        $value = trim((string)$xml->{$field});
        if ($value === '') {
            throw new ParseException("{$context} is missing a {$field} field");
        }

        return $value;
    }

    /**
     * Parse a SOCS co-curricular date and time in the Schoolbox timezone.
     *
     * @return DateTimeImmutable
     * @throws ParseException
     */
    private function parseDateTime(string $date, string $time, string $eventId): DateTimeImmutable
    {
        $format = str_contains($time, ':') && substr_count($time, ':') === 2
            ? '!d/m/Y H:i:s'
            : '!d/m/Y H:i';
        $raw = sprintf('%s %s', $date, $time);
        $dateTime = DateTimeImmutable::createFromFormat($format, $raw, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$dateTime instanceof DateTimeImmutable
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new ParseException(
                sprintf('Unable to parse date/time "%s" for SOCS co-curricular event %s', $raw, $eventId)
            );
        }

        return $dateTime;
    }
}
