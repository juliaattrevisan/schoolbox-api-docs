<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use DateTimeZone;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Sabre\VObject\ParseException;

/**
 * Parse SOCS co-curricular XML feeds into a complete normalized data set.
 */
class Parser
{
    public function __construct(private LoggerInterface $log)
    {
    }

    /**
     * Parse and validate the clubs, events, and registers feeds together.
     *
     * @return array{
     *     0:array<string,Club>,
     *     1:array<string,Event>,
     *     2:array<string,string[]>,
     *     3:array<string,string[]>
     * }
     * @throws ParseException When a feed has an invalid root
     */
    public function parse(
        \SimpleXMLElement $clubsXml,
        \SimpleXMLElement $eventsXml,
        \SimpleXMLElement ...$registersXml,
    ): array {
        return $this->parseWithEvents($clubsXml, $this->parseEvents($eventsXml), ...$registersXml);
    }

    /**
     * Parse clubs and registers using events that have already been normalized.
     *
     * @param array<string,Event> $events
     * @param \SimpleXMLElement ...$registersXml
     * @return array{
     *     0:array<string,Club>,
     *     1:array<string,Event>,
     *     2:array<string,string[]>,
     *     3:array<string,string[]>
     * }
     * @throws ParseException When a feed has an invalid root
     */
    public function parseWithEvents(
        \SimpleXMLElement $clubsXml,
        array $events,
        \SimpleXMLElement ...$registersXml,
    ): array {
        $this->requireRoot($clubsXml, 'clubs');

        $clubs = $this->parseClubs($clubsXml);
        $pupilIdsByClubId = $this->parseRegisters($registersXml, $events);
        $staffIdsByClubId = $this->parseStaff($events);

        return [$clubs, $events, $pupilIdsByClubId, $staffIdsByClubId];
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
     */
    private function parseClubs(\SimpleXMLElement $xml): array
    {
        $clubs = [];

        foreach ($xml->club as $index => $clubXml) {
            $number = ((int)$index) + 1;
            try {
                $id = $this->requireValue($clubXml, 'clubid', "SOCS co-curricular club #{$number}");
                if (isset($clubs[$id])) {
                    continue;
                }
                $name = $this->requireValue($clubXml, 'clubname', "SOCS co-curricular club #{$number}");
                $category = $this->requireValue($clubXml, 'category', "SOCS co-curricular club #{$number}");

                $clubs[$id] = new Club($id, $name, $category);
            } catch (ParseException $e) {
                $this->logMalformedRecord($e);
            }
        }

        return $clubs;
    }

    /**
     * Parse unique event occurrences indexed by SOCS EventID.
     *
     * @return array<string,Event>
     * @throws ParseException When the feed has an invalid root
     */
    public function parseEvents(\SimpleXMLElement $xml): array
    {
        $this->requireRoot($xml, 'events');
        $events = [];

        foreach ($xml->event as $index => $eventXml) {
            $number = ((int)$index) + 1;
            $context = "SOCS co-curricular event #{$number}";
            try {
                $id = $this->requireValue($eventXml, 'eventid', $context);
                if (isset($events[$id])) {
                    continue;
                }
                $eventContext = "SOCS co-curricular event {$id}";
                $clubId = $this->requireValue($eventXml, 'clubid', $eventContext);
                $title = $this->requireValue($eventXml, 'title', $eventContext);
                $date = $this->requireValue($eventXml, 'startdate', $eventContext);
                $allDay = strtolower(trim((string)$eventXml->alldayevent)) === 'true';

                if ($allDay) {
                    $start = $this->parseDateTime($date, '00:00:00', $id);
                    $finish = $this->parseDateTime($date, '23:59:59', $id);
                } else {
                    $startTime = $this->requireValue($eventXml, 'starttime', $eventContext);
                    $endTime = $this->requireValue($eventXml, 'endtime', $eventContext);
                    $start = $this->parseDateTime($date, $startTime, $id);
                    $finish = $this->parseDateTime($date, $endTime, $id);
                }

                if ($finish->lt($start)) {
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
                    array_values(array_filter(array_map('trim', explode(',', (string)$eventXml->staff)), 'strlen')),
                );
            } catch (ParseException $e) {
                $this->logMalformedRecord($e);
            }
        }

        return $events;
    }

    /**
     * Union staff IDs supplied on event records by ClubID for team coaches.
     *
     * @param array<string,Event> $events
     * @return array<string,string[]>
     */
    private function parseStaff(array $events): array
    {
        $staffByClubId = [];
        foreach ($events as $event) {
            foreach ($event->staffIds as $staffId) {
                if (!in_array($staffId, $staffByClubId[$event->clubId] ?? [], true)) {
                    $staffByClubId[$event->clubId][] = $staffId;
                }
            }
        }

        return $staffByClubId;
    }

    /**
     * Union pupil IDs across every event belonging to each ClubID.
     *
     * SOCS pupil records are occurrence-specific. This union intentionally supports
     * the Fixtures team-membership visibility model used by the importer.
     *
     * @param \SimpleXMLElement[] $registersXml
     * @param array<string,Event> $events
     * @return array<string,string[]>
     * @throws ParseException When a feed has an invalid root
     */
    private function parseRegisters(array $registersXml, array $events): array
    {
        $pupilIdSetsByClubId = [];
        $number = 0;

        foreach ($registersXml as $xml) {
            $this->requireRoot($xml, 'registers');
            foreach ($xml->pupil as $pupilXml) {
                $number++;
                try {
                    $context = "SOCS co-curricular register #{$number}";
                    $eventId = $this->requireValue($pupilXml, 'eventid', $context);
                    $pupilId = $this->requireValue($pupilXml, 'pupilid', $context);

                    if (
                        isset($events[$eventId])
                        && !in_array($pupilId, $pupilIdSetsByClubId[$events[$eventId]->clubId] ?? [], true)
                    ) {
                        $pupilIdSetsByClubId[$events[$eventId]->clubId][] = $pupilId;
                    }
                } catch (ParseException $e) {
                    $this->logMalformedRecord($e);
                }
            }
        }

        return $pupilIdSetsByClubId;
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
     * @throws ParseException
     */
    private function parseDateTime(string $date, string $time, string $eventId): \Alaress_Date_Format
    {
        $raw = sprintf('%s %s', $date, $time);
        try {
            $dateTime = new \Alaress_Date_Format(
                $raw,
                [
                    '!' . \Alaress_Date_Format::FORMAT_DATE_ALT . ' H:i:s',
                    '!' . \Alaress_Date_Format::FORMAT_DATE_ALT . ' H:i',
                ],
                new DateTimeZone(date_default_timezone_get()),
            );
        } catch (InvalidArgumentException $e) {
            throw new ParseException(
                sprintf('Unable to parse date/time "%s" for SOCS co-curricular event %s', $raw, $eventId),
                0,
                $e,
            );
        }
        if (!$dateTime->isValid()) {
            throw new ParseException(
                sprintf('Unable to parse date/time "%s" for SOCS co-curricular event %s', $raw, $eventId)
            );
        }

        return $dateTime;
    }

    private function logMalformedRecord(ParseException $exception): void
    {
        $this->log->info(sprintf('Skipping malformed %s', $exception->getMessage()));
    }
}
