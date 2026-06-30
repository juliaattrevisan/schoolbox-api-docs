<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Music;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\ParseException;
use SimpleXMLElement;

/**
 * Parses SOCS tuition music lesson feeds into normalized lesson value objects.
 */
class Parser
{
    private DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone(date_default_timezone_get());
    }

    /**
     * @return Lesson[]
     * @throws ParseException
     */
    public function parse(SimpleXMLElement $xml): array
    {
        if (strcasecmp($xml->getName(), 'lessons') !== 0) {
            throw new ParseException('SOCS music lesson XML feed must use lessons as the root element');
        }

        $lessons = [];
        foreach ($xml->xpath('//*[local-name()="lesson"]') ?: [] as $index => $lessonNode) {
            $lessons[] = $this->buildLesson($lessonNode, $index + 1);
        }

        return $lessons;
    }

    /**
     * @throws ParseException
     */
    private function buildLesson(SimpleXMLElement $lessonNode, int $lessonNumber): Lesson
    {
        $id = $this->getChildValue($lessonNode, ['lessonid', 'lessonId', 'id']);
        if ($id === '') {
            throw new ParseException("SOCS music lesson #{$lessonNumber} is missing a lessonid field");
        }

        $startDate = $this->getChildValue($lessonNode, ['startdate', 'startDate', 'date']);
        if ($startDate === '') {
            throw new ParseException("SOCS music lesson {$id} is missing a startdate field");
        }

        $start = $this->parseDateTime(
            $startDate,
            $this->getChildValue($lessonNode, ['starttime', 'startTime']),
            "start date for SOCS music lesson {$id}"
        );
        $end = $this->parseDateTime(
            $startDate,
            $this->getChildValue($lessonNode, ['endtime', 'endTime']),
            "end date for SOCS music lesson {$id}"
        );
        if ($end < $start) {
            throw new ParseException("SOCS music lesson {$id} has an end time before its start time");
        }

        $instrument = $this->getChildValue($lessonNode, ['instrument']);
        $title = $this->getChildValue($lessonNode, ['title', 'name', 'subject']);
        if ($title === '') {
            $title = $instrument !== '' ? $instrument . ' lesson' : 'Music lesson';
        }

        return new Lesson(
            $id,
            $start,
            $end,
            $title,
            $instrument,
            $this->getChildValue($lessonNode, ['location', 'venue', 'room']),
            $this->getChildValue($lessonNode, ['staffid', 'staffId']),
            $this->getChildValue($lessonNode, ['pupilid', 'pupilId', 'studentid', 'studentId']),
        );
    }

    /**
     * @throws ParseException
     */
    private function parseDateTime(string $date, string $time, string $fieldDescription): DateTimeImmutable
    {
        $value = trim(trim($date) . ' ' . trim($time));
        $formats = trim($time) === ''
            ? ['!d/m/Y', '!Y-m-d', '!d-m-Y', '!m/d/Y', '!j M Y']
            : ['!d/m/Y H:i', '!Y-m-d H:i', '!d-m-Y H:i', '!m/d/Y H:i', '!j M Y H:i', '!d/m/Y g:i A', '!m/d/Y g:i A'];

        foreach ($formats as $format) {
            $dateTime = DateTimeImmutable::createFromFormat($format, $value, $this->timezone);
            if ($dateTime instanceof DateTimeImmutable && $this->dateParsedCleanly()) {
                return $dateTime;
            }
        }

        throw new ParseException("Could not parse {$fieldDescription}: {$value}");
    }

    private function dateParsedCleanly(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    /**
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
