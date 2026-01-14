<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller;

/**
 * Class ExistingImports
 * @package Schoolbox\Assessment\Course\Import
 */
class ExistingImports
{
    /**
     * Course Outline Component controller
     */
    private const COMPONENT_CONTROLLER = Controller::class;


    /**
     * Component configuration key - course spec ids live in this config instance
     */
    private const COMPONENT_KEY = 'importedCourse';


    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * ExistingImports constructor.
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * Find a list of homepages which have this course specification id imported into
     * at least one Course Outline component
     *
     * @param int $courseSpecificationId
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(int $courseSpecificationId): array
    {
        $queryParts = [];

        /* Find all the Course implementation which have previously been imported but archived
         * as well as active ones
         */
        $query = "
            SELECT DISTINCT course_implementation.folder_id,
                            course_implementation.course_specification_id
            FROM course_implementation
            WHERE course_specification_id = :courseSpecificationId
            AND course_implementation.deleted_at IS NULL
            ORDER BY folder_id ASC
        ";

        $result = $this->database->executeQuery($query, [
            'courseSpecificationId' => $courseSpecificationId
        ]);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row['folder_id'];
        }

        return $stack;
    }
}
