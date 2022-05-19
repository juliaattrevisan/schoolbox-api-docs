<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Acgs\NccdDownload;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Application\CliTool\App;

/**
 * Command-line tool for downloading files from NCCD courses.
 */
class CommandLineTool extends App
{
    /**
     * The ID(s) of the NCCD information courses.
     */
    private const COURSE_IDS = [468];

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        // Find every folder which imports the NCCD Information course
        $params = Builder::getArrayPlaceholders(self::COURSE_IDS);
        $query  = "
            SELECT f.id, f.name, ci.id AS course_implementation_id
            FROM folder f
            INNER JOIN course_implementation ci ON f.id = ci.folder_id
            INNER JOIN course_specification  cs ON ci.course_specification_id = cs.id
            WHERE f.deleted_at IS NULL
            AND   ci.deleted_at IS NULL
            AND   cs.deleted_at IS NULL
            AND   cs.id IN {$params}
            ORDER BY f.name, f.id
        ";
        $statement = $this->getDatabaseInternal()->executeQuery($query, Builder::getArrayParameters(self::COURSE_IDS));

        $logger = $this->getLogger();
        $logger->info(sprintf('Found %d folders which have imported the NCCD courses:', $statement->rowCount()));
        foreach ($statement->getIterator() as $row) {
            $logger->info(sprintf('#%d (%s)', $row['id'], $row['name']));
        }
    }
}
