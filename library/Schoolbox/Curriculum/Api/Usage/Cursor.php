<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Connector\IConnector;
use PDO;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use ValueError;

/**
 * Cursor for curriculum usage results.
 *
 * Fetches a list of names and identifiers of curriculum usages, for conversion
 * into an actual set of records by `\Schoolbox\Curriculum\Api\Usage\Hydrator`.
 */
class Cursor implements ICursor
{
    /**
     * Discriminator for identifying a course result.
     */
    public const DISCRIMINATOR_COURSE = 'course';

    /**
     * Discriminator for identifying a unit result.
     */
    public const DISCRIMINATOR_UNIT = 'unit';

    /**
     * Discriminator for identifying a folder result.
     */
    public const DISCRIMINATOR_FOLDER = 'folder';

    /**
     * Minimum number of results returnable from the cursor at once:
     * it must return at least one result.
     */
    public const LIMIT_MIN = 1;

    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 100 results is a sensible maximum.
     */
    public const LIMIT_MAX = 100;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    /**
     * Constructor
     *
     * @param IConnector  $database The database connection to retrieve from.
     * @param Node\Model  $node     The node to fetch usages for.
     * @param Counter     $counter  The counter used for fetching usage counts in an efficient manner.
     * @param int         $limit    Max number of results to fetch at once
     * @param ICodec|null $codec    If provided: use this codec for encoding/decoding cursors
     */
    public function __construct(
        private IConnector $database,
        private Node\Model $node,
        private Counter $counter,
        private int $limit,
        private ?ICodec $codec
    ) {
        $this->codec ??= new Base64();
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(sprintf('$limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit));
        }
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('$limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }
    }

    /**
     * @inheritDoc
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be an array
        return $this->codec->encode($collection->last());
    }

    /**
     * @inheritDoc
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function retrieveResultCount(): ?int
    {
        $totalCount = 0;
        $counts = $this->counter->findUsageCounts([$this->node->getId()])[$this->node->getId()] ?? [];
        foreach ($counts as $subCount) {
            $totalCount += $subCount;
        }
        return $totalCount;
    }

    /**
     * @return Collection<array{id: int, name: string, discriminator: string}>
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $selectParts = [
            "
                SELECT
                    :courseDiscriminator AS discriminator,
                    cs.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                INNER JOIN course_specification cs ON f.id = cs.folder_id
                WHERE f.deleted_at IS NULL
                AND cs.deleted_at IS NULL
                AND cnfc.curriculum_node_id = :courseCurriculumNodeId
            ",
            "
                SELECT
                    :unitDiscriminator AS discriminator,
                    cus.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                INNER JOIN course_unit_specification cus ON f.id = cus.folder_id
                WHERE f.deleted_at IS NULL
                AND cus.deleted_at IS NULL
                AND cnfc.curriculum_node_id = :unitCurriculumNodeId
            ",
            "
                SELECT
                    :folderDiscriminator AS discriminator,
                    f.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id
                LEFT JOIN course_specification cs ON f.id = cs.folder_id
                LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id
                WHERE f.deleted_at IS NULL
                AND cs.id IS NULL
                AND cus.id IS NULL
                AND cnfc.curriculum_node_id = :folderCurriculumNodeId
            ",
        ];
        $queryString = 'SELECT * FROM (('
            . implode(') UNION (', $selectParts)
            . ')) AS combined';
        $params = [
            'courseDiscriminator'    => self::DISCRIMINATOR_COURSE,
            'courseCurriculumNodeId' => $this->node->getId(),
            'unitDiscriminator'      => self::DISCRIMINATOR_UNIT,
            'unitCurriculumNodeId'   => $this->node->getId(),
            'folderDiscriminator'    => self::DISCRIMINATOR_FOLDER,
            'folderCurriculumNodeId' => $this->node->getId(),
        ];

        // Figure out a WHERE part
        if (isset($encodedCursor)) {
            $decodedCursor = $this->codec->decode($encodedCursor);
            if (isset($decodedCursor['name'], $decodedCursor['id'], $decodedCursor['discriminator'])) {
                $whereParts    = [
                    '(name > :name1)',
                    '(name = :name2 AND id > :id2)',
                    '(name = :name3 AND id = :id3 AND discriminator > :discriminator3)',
                ];
                $queryString .= ' WHERE ' . implode(' OR ', $whereParts);
                $params += [
                    'name1'          => $decodedCursor['name'],
                    'name2'          => $decodedCursor['name'],
                    'name3'          => $decodedCursor['name'],
                    'id2'            => $decodedCursor['id'],
                    'id3'            => $decodedCursor['id'],
                    'discriminator3' => $decodedCursor['discriminator'],
                ];
            }
        }

        // Build the rest of the query
        $queryString .= '
            ORDER BY name ASC, id ASC, discriminator ASC
            LIMIT :limit
        ';
        $params['limit'] = [
            'type'  => PDO::PARAM_INT,
            'value' => $this->limit,
        ];

        $query = $this->database->prepareQuery($queryString);
        $query->bindInputs($params);
        $query->execute();

        // Use this list of results to hydrate actual objects in the Hydrator
        return new Collection($query->fetchAll());
    }
}
