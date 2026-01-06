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

        /* Find all folder ids which have a Course Outline component
         * with this course specification id imported
         */
        $queryParts[] = "
            SELECT DISTINCT
                ci.identifier AS folder_id,
                course_implementation.course_specification_id
            FROM component_definition cd
            INNER JOIN component_instance ci ON cd.id = ci.component_definition_id
            INNER JOIN component_config_instance cci ON cci.component_instance_id = ci.id
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            INNER JOIN course_implementation ON cci.value = course_implementation.id
            INNER JOIN folder f ON ci.identifier = f.id
            WHERE cd.class = :controller
            AND ccd.key = :key
            AND course_specification_id = :courseSpecificationId
            AND f.deleted_at IS NULL
        ";

        /* Find all the Course implementation which have previously been imported but
         * the Course Outline component was removed (ie in archived state)
         */
        $queryParts[] = "
            SELECT DISTINCT course_implementation.folder_id,
                            course_implementation.course_specification_id
            FROM course_implementation
            WHERE course_specification_id = :courseSpecificationId
              AND course_implementation.deleted_at IS NULL
        ";

        $query = implode(' UNION ', $queryParts);

        $query .= " ORDER BY folder_id ASC ";

        $result = $this->database->executeQuery($query, [
            'controller' => self::COMPONENT_CONTROLLER,
            'key' => self::COMPONENT_KEY,
            'courseSpecificationId' => $courseSpecificationId
        ]);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row['folder_id'];
        }

        return $stack;
    }
}
