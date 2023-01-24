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
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;

class Cursor implements ICursor
{
    private const DISCRIMINATOR_COURSE = 'course';

    private const DISCRIMINATOR_UNIT = 'unit';

    private const DISCRIMINATOR_FOLDER = 'folder';

    /**
     * Constructor
     *
     * @param IConnector  $database The database connection to retrieve from.
     * @param Node\Model  $node     The node to fetch usages for.
     * @param ICodec|null $codec    If provided: use this codec for encoding/decoding cursors
     */
    public function __construct(
        private IConnector $database,
        private Node\Model $node,
        private ?ICodec $codec
    ) {
        $this->codec ??= new Base64();
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
        $query = $this->database->prepareQuery('
            SELECT COUNT(*) AS count
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            INNER JOIN course_specification cs ON f.id = cs.folder_id
            WHERE f.deleted_at IS NULL
            AND cs.deleted_at IS NULL
            AND cnfc.curriculum_node_id = :courseCurriculumNodeId

            UNION

            SELECT COUNT(*) AS count
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            INNER JOIN course_unit_specification cus ON f.id = cus.folder_id
            WHERE f.deleted_at IS NULL
            AND cus.deleted_at IS NULL
            AND cnfc.curriculum_node_id = :unitCurriculumNodeId

            UNION

            SELECT COUNT(*) AS count
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            LEFT JOIN course_specification cs ON f.id = cs.folder_id
            LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id
            WHERE f.deleted_at IS NULL
            AND cs.id IS NULL
            AND cus.id IS NULL
            AND cnfc.curriculum_node_id = :folderCurriculumNodeId
        ');
        $query->execute([
            'courseCurriculumNodeId' => $this->node->getId(),
            'unitCurriculumNodeId'   => $this->node->getId(),
            'folderCurriculumNodeId' => $this->node->getId(),
        ]);
        $count = 0;
        foreach ($query->fetchColumnArray('count') as $counts) {
            $count += $counts;
        }
        return $count;
    }

    /**
     * @inheritDoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        // Courses
        $query = $this->database->prepareQuery('
            SELECT :courseDiscriminator AS discriminator,
                   cs.id AS id
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            INNER JOIN course_specification cs ON f.id = cs.folder_id
            WHERE f.deleted_at IS NULL
            AND cs.deleted_at IS NULL
            AND cnfc.curriculum_node_id = :courseCurriculumNodeId

            UNION

            SELECT :unitDiscriminator AS discriminator,
                   cus.id AS id
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            INNER JOIN course_unit_specification cus ON f.id = cus.folder_id
            WHERE f.deleted_at IS NULL
            AND cus.deleted_at IS NULL
            AND cnfc.curriculum_node_id = :unitCurriculumNodeId

            UNION

            SELECT :folderDiscriminator AS discriminator,
                   f.id AS id
            FROM curriculum_node_for_category cnfc
            INNER JOIN folder f ON cnfc.category_id = f.id
            LEFT JOIN course_specification cs ON f.id = cs.folder_id
            LEFT JOIN course_unit_specification cus ON f.id = cus.folder_id
            WHERE f.deleted_at IS NULL
            AND cs.id IS NULL
            AND cus.id IS NULL
            AND cnfc.curriculum_node_id = :folderCurriculumNodeId
        ');
        $query->execute([
            'courseDiscriminator'    => self::DISCRIMINATOR_COURSE,
            'courseCurriculumNodeId' => $this->node->getId(),
            'unitDiscriminator'      => self::DISCRIMINATOR_UNIT,
            'unitCurriculumNodeId'   => $this->node->getId(),
            'folderDiscriminator'    => self::DISCRIMINATOR_FOLDER,
            'folderCurriculumNodeId' => $this->node->getId(),
        ]);

        return new Collection($query->fetchAll());
    }
}
