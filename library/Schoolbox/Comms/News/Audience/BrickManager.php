<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use InvalidArgumentException;
use PDO;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use UnexpectedValueException;

use function array_values;

/**
 * Responsible for generating a set of Bricks for an article
 *
 * Bricks are an aggregation of audience segments, suitable for
 * generating efficient SQL queries.
 */
class BrickManager
{
    /** @var Database\Connector\IConnector */
    private $db;


    /**
     * @param Database\Connector\IConnector $db
     */
    public function __construct(Database\Connector\IConnector $db)
    {
        $this->db = $db;
    }


    /**
     * Generate a set of audience bricks for the specified article
     *
     * @param int $articleId
     * @return GenericCollection|Brick[]
     * @throws Database\Exception
     * @throws InvalidArgumentException
     */
    public function generateBricks(int $articleId): GenericCollection
    {
        $bricks = Brick::createCollection();

        $this->addSimpleSegments($bricks, $articleId);
        $this->addComplexSegments($bricks, $articleId);

        return $bricks;
    }


    /**
     * Generate a set of Bricks from an article's audience
     *
     * @param GenericCollection|Model[] $audience
     * @return GenericCollection|Brick[]
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     */
    public function generateBricksFromAudience(GenericCollection $audience): GenericCollection
    {
        /** @var GenericCollection|Brick[] $bricks */
        $bricks = new GenericCollection(Brick::class);

        foreach ($audience as $segment) {
            if ($segment->isSimple()) {
                $newBrick = new Brick(
                    [new Dimension($segment->getEntityType(), $segment->getEntityValue())],
                    $segment->getRelationship()
                );
                $this->addNewBrick($bricks, $newBrick);
                continue;
            }
            /** @var Model $child1 */
            /** @var Model $child2 */
            [$child1, $child2] = array_values($segment->getChildren()->getAll());
            $newBrick = new Brick(
                [
                    new Dimension($child1->getEntityType(), $child1->getEntityValue()),
                    new Dimension($child2->getEntityType(), $child2->getEntityValue()),
                ],
                $segment->getRelationship()
            );
            $this->addNewBrick($bricks, $newBrick);
        }

        return $bricks;
    }


    /**
     * @param GenericCollection $bricks
     * @param int               $articleId
     * @throws Database\Exception
     * @throws InvalidArgumentException
     */
    private function addSimpleSegments(GenericCollection $bricks, int $articleId): void
    {
        $sql = "
            SELECT entity_type, entity_value, relationship
            FROM comms_news_audience
            WHERE comms_news_article_id = :article_id
        ";

        $query = $this->db->executeQuery($sql, ['article_id' => $articleId]);
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $newBrick = new Brick(
                [new Dimension($row['entity_type'], $row['entity_value'])],
                $row['relationship']
            );
            $this->addNewBrick($bricks, $newBrick);
        }
    }


    /**
     * @param GenericCollection|Brick[] $bricks
     * @param int                       $articleId
     * @throws Database\Exception
     * @throws InvalidArgumentException
     */
    private function addComplexSegments(GenericCollection $bricks, int $articleId): void
    {
        $sql = "
            SELECT entity_type_1, entity_value_1, entity_type_2, entity_value_2
            FROM comms_news_audience_complex
            WHERE comms_news_article_id = :article_id
            ORDER BY entity_value_1 DESC, entity_type_2 DESC
        ";

        $query = $this->db->executeQuery($sql, ['article_id' => $articleId]);
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $newBrick = new Brick(
                [
                    new Dimension($row['entity_type_1'], $row['entity_value_1']),
                    new Dimension($row['entity_type_2'], $row['entity_value_2']),
                ],
                Model::RELATIONSHIP_SELF
            );
            $this->addNewBrick($bricks, $newBrick);
        }
    }


    /**
     * Adds a new brick, combining with an existing brick if possible
     *
     * @param GenericCollection|Brick[] $bricks
     * @param Brick                     $newBrick
     * @throws InvalidArgumentException
     */
    private function addNewBrick(GenericCollection $bricks, Brick $newBrick): void
    {
        foreach ($bricks as $brick) {
            if ($brick->contains($newBrick)) {
                // New brick is a subset of existing bricks, nothing to do
                return;
            }
            if ($brick->addBrick($newBrick)) {
                // Examine whether adding brick can not be combined with another existing brick
                $this->collapseBricks($bricks, $brick);
                return;
            }
        }
        // Not contained, and unable to be absorbed. Add as distinct new brick.
        $bricks->add($newBrick);
    }


    /**
     * Checks whether the specified brick can be combined with any other brick.
     *
     * @param GenericCollection|Brick[] $bricks
     * @param Brick                     $changedBrick
     * @throws InvalidArgumentException
     */
    private function collapseBricks(GenericCollection $bricks, Brick $changedBrick): void
    {
        foreach ($bricks as $brick) {
            if ($brick === $changedBrick) {
                continue;
            }
            if ($brick->addBrick($changedBrick)) {
                $this->removeBrick($bricks, $changedBrick);
                $this->collapseBricks($bricks, $brick);
                return;
            }
        }
    }


    /**
     * Removes the specified brick from the collection
     *
     * @param GenericCollection $bricks
     * @param Brick             $brickToRemove
     * @return bool
     */
    private function removeBrick(GenericCollection $bricks, Brick $brickToRemove): bool
    {
        foreach ($bricks as $idx => $brick) {
            if ($brick === $brickToRemove) {
                $bricks->offsetUnset($idx);
                return true;
            }
        }
        return false;
    }
}
