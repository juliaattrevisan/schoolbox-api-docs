<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;

/**
 * Factory for building a curriculum object from a response from the Atlas API.
 */
class CurriculumFactory
{
    /**
     * Build a curriculum from a response.
     *
     * @param array $response A single curriculum from the Atlas API.
     *                        (@see https://api.rubicon.com/docs/#!/Curriculum/get_curriculummap_id)
     * @return Curriculum
     */
    public function build(array $response): Curriculum
    {
        $curriculum = new Curriculum();
        if (array_key_exists('id', $response)) {
            $curriculum = $curriculum->setId((int)$response['id']);
        }
        if (array_key_exists('title', $response)) {
            $curriculum = $curriculum->setTitle((string)$response['title']);
        }
        if (array_key_exists('subject', $response)) {
            $curriculum = $curriculum->setSubject((string)$response['subject']);
        }
        if (array_key_exists('grade', $response)) {
            $curriculum = $curriculum->setGrade((string)$response['grade']);
        }
        if (array_key_exists('school', $response)) {
            $curriculum = $curriculum->setSchool((string)$response['school']);
        }
        if (array_key_exists('description', $response)) {
            $curriculum = $curriculum->setDescription((string)$response['description']);
        }
        if (array_key_exists('last_updated', $response)) {
            $curriculum = $curriculum->setLastUpdated(
                new Alaress_Date_Format(
                    $response['last_updated'],
                    Alaress_Date_Format::DATABASE_FORMAT_MS
                )
            );
        }
        if (array_key_exists('map_type', $response)) {
            $curriculum = $curriculum->setMapType((string)$response['map_type']);
        }
        return $curriculum;
    }
}
