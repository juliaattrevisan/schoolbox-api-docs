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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\IConfigLoader;
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
     * Discriminator for identifying an assessment result.
     *
     * (Only specification assessments are currently retrieved by this API.)
     */
    public const DISCRIMINATOR_ASSESSMENT = 'assessment';

    /**
     * Discriminator for identifying an assessment within a project result.
     *
     * (Only specification assessments are currently retrieved by this API.)
     */
    public const DISCRIMINATOR_PROJECT_ASSESSMENT = 'projectAssessment';

    /**
     * Discriminator for identifying a badge.
     *
     */
    public const DISCRIMINATOR_BADGE = 'badge';

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
     * @param IConnector    $database     The database connection to retrieve from.
     * @param IConfigLoader $configLoader The config settings
     * @param Node\Model    $node         The node to fetch usages for.
     * @param Counter       $counter      The counter used for fetching usage counts in an efficient manner.
     * @param int           $limit        Max number of results to fetch at once
     * @param ICodec|null   $codec        If provided: use this codec for encoding/decoding cursors
     */
    public function __construct(
        private IConnector $database,
        private IConfigLoader $configLoader,
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
        // Order:
        // - Courses
        // - Units
        // - Assessments
        // - Badges
        // - Folders
        $selectParts = [
            "
                SELECT
                    1 AS discriminator_order,
                    :courseDiscriminator AS discriminator,
                    cs.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                INNER JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
                WHERE cnfc.curriculum_node_id = :courseCurriculumNodeId
            ",
            "
                SELECT
                    2 AS discriminator_order,
                    :unitDiscriminator AS discriminator,
                    cus.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                INNER JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
                WHERE cnfc.curriculum_node_id = :unitCurriculumNodeId
            ",
            "
                SELECT
                    3 AS discriminator_order,
                    :assessmentDiscriminator AS discriminator,
                    a.id AS id,
                    a.title AS name
                FROM curriculum_node_for_assessment cnfa
                INNER JOIN mv_assessment_relation var ON cnfa.assessment_id = var.assessment_id
                INNER JOIN assessment a ON var.assessment_id = a.id AND a.deleted_at IS NULL
                INNER JOIN course_unit_specification cus
                    ON var.course_unit_specification_id = cus.id AND cus.deleted_at IS NULL
                WHERE cnfa.curriculum_node_id = :assessmentCurriculumNodeId
            ",
            "
                SELECT
                    5 AS discriminator_order,
                    :folderDiscriminator AS discriminator,
                    f.id AS id,
                    f.name AS name
                FROM curriculum_node_for_category cnfc
                INNER JOIN folder f ON cnfc.category_id = f.id AND f.deleted_at IS NULL
                LEFT JOIN course_specification cs ON f.id = cs.folder_id AND cs.deleted_at IS NULL
                LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id AND cus.deleted_at IS NULL
                WHERE cs.id IS NULL
                AND cus.id IS NULL
                AND cnfc.curriculum_node_id = :folderCurriculumNodeId
            ",
        ];

        $params = [];
        if ($this->configLoader->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $selectParts[] = "
                SELECT
                    4 AS discriminator_order,
                    :badgeDiscriminator AS discriminator,
                    b.id AS id,
                    b.name AS name
                FROM curriculum_badge cb
                JOIN badge b on (cb.badge_id = b.id AND b.deleted_at IS NULL)
                WHERE cb.curriculum_node_id = :badgeCurriculumNodeId
        ";
            $params += [
                'badgeDiscriminator'         => self::DISCRIMINATOR_BADGE,
                'badgeCurriculumNodeId'      => $this->node->getId(),
            ];
        }

        $queryString = 'SELECT * FROM (('
            . implode(') UNION ALL (', $selectParts)
            . ')) AS combined';

        $params += [
            'courseDiscriminator'        => self::DISCRIMINATOR_COURSE,
            'courseCurriculumNodeId'     => $this->node->getId(),
            'unitDiscriminator'          => self::DISCRIMINATOR_UNIT,
            'unitCurriculumNodeId'       => $this->node->getId(),
            'assessmentDiscriminator'    => self::DISCRIMINATOR_ASSESSMENT,
            'assessmentCurriculumNodeId' => $this->node->getId(),
            'folderDiscriminator'        => self::DISCRIMINATOR_FOLDER,
            'folderCurriculumNodeId'     => $this->node->getId(),
        ];

        // Figure out a WHERE part
        if (isset($encodedCursor)) {
            $decodedCursor = $this->codec->decode($encodedCursor);
            if (isset($decodedCursor['discriminator_order'], $decodedCursor['name'], $decodedCursor['id'])) {
                $whereParts    = [
                    '(discriminator_order > :discriminatorOrder1)',
                    '(discriminator_order = :discriminatorOrder2 AND name > :name2)',
                    '(discriminator_order = :discriminatorOrder3 AND name = :name3 AND id > :id3)',
                ];
                $queryString .= ' WHERE ' . implode(' OR ', $whereParts);
                $params += [
                    'discriminatorOrder1' => $decodedCursor['discriminator_order'],
                    'discriminatorOrder2' => $decodedCursor['discriminator_order'],
                    'discriminatorOrder3' => $decodedCursor['discriminator_order'],
                    'name2'               => $decodedCursor['name'],
                    'name3'               => $decodedCursor['name'],
                    'id3'                 => $decodedCursor['id'],
                ];
            }
        }

        // Build the rest of the query
        $queryString .= '
            ORDER BY discriminator_order ASC, name ASC, id ASC
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
