<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;

/**
 * Index builder for pages/folders.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with folder objects.
     *
     * TODO It could also work on folder code, course specification, course
     *      implementation, homepage config and user group objects, if they
     *      return the folder ID?
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        return $subject instanceof Model;
    }

    /**
     * @param BuildType $buildType
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        return [
            $baseQuery . ' AND f.id = :id',
            $baseParams + ['id' => $subject->getId()],
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                f.id,
                f.name,
                GROUP_CONCAT(fc.code ORDER BY fc.code ASC SEPARATOR ',') AS folderCodes,
                f.updated_at,
                f.created_by,
                " . $this->buildTypeExpression() . " AS type
            FROM folder f
            " . $this->getJoinsFromFolderForTypeExpression() . "
            WHERE f.deleted_at IS NULL
            GROUP BY f.id
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND f.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND f.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::UPDATE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding pages/folders...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d pages/folders', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['name'] . ((strlen($row['folderCodes'] ?? '') > 0) ? " ({$row['folderCodes']})" : ''),
                $row['type'],
                $row['updated_at'],
                false, // Pages/folders are always maximally relevant.
                $row['created_by'],
                null,
            );

            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                f.id,
                " . $this->buildTypeExpression() . " AS type
            FROM folder f
            " . $this->getJoinsFromFolderForTypeExpression() . "
            WHERE f.deleted_at IS NOT NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND f.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND f.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::DELETE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::DELETE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d pages/folders', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                $row['type'],
                null,
                false,
                null,
                null,
            );
        }
    }

    /**
     * A page's type depends on what sort of page it is, in the
     * following order of preference:
     * * course: pages with a related course specification
     * * unit: pages with a related unit specification
     * * class: pages with a class code
     * * group: pages with a user group
     * * homepage: pages with no class code, but a homepage
     * * folder: pages without a homepage
     *
     * @inheritdoc
     */
    private function buildTypeExpression(): string
    {
        // NVL2: if param 1 is not null returns param 2, otherwise param 3 (https://mariadb.com/kb/en/nvl2/)
        return "CONCAT(
            '" . Hydrator::BASE_TYPE . ".',
            NVL2(
                cs.id,
                'course',
                NVL2(
                    cus.id,
                    'unit',
                    NVL2(
                        fc.code,
                        'class',
                        NVL2(
                            ug.cid,
                            'group',
                            NVL2(hc.template, 'homepage', 'folder')
                        )
                    )
                )
            )
        )";
    }

    /**
     * Return the SQL JOIN clauses from table `folder` that are necessary for $this->buildTypeExpression().
     * @returns string
     */
    private function getJoinsFromFolderForTypeExpression(): string
    {
        return "
            LEFT JOIN folder_code fc ON f.id = fc.folder_id AND fc.deleted_at IS NULL
            LEFT JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
            LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
            LEFT JOIN homepage_config hc ON f.id = hc.cid
            LEFT JOIN userGroups ug ON f.id = ug.cid
        ";
    }
}
