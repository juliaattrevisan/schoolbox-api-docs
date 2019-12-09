#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Comms\News;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Deletes duplicates audience segments
 *
 * We need to perform a root-first traversal, eg consider:
 *
 * Audience:
 * ├─A
 * | ├─B
 * | ├─B
 * | └-C
 * └─D
 *   ├─B
 *   └-C
 *
 * If we did root-first, A != D, so both would remain. We would then remove an A > B, but would now have A,D duplicates.
 * If we do leaf-first, we will first remove A > B, then A == D, so one of them would be removed.
 */
$app = new class extends CliTool\App
{

    private $countRootSimple        = 0;  // Simple roots
    private $countRootComposites    = 0;  // Root composites
    private $countCompositeChildren = 0;  // Composites' simple children
    private $countTotal             = 0;

    /** @inheritDoc */
    public function run(): void
    {
        $this->getDatabaseInternal()->beginTransaction();
        try {
            foreach ($this->getArticleIds() as $articleId) {
                $this->processArticle($articleId);
            }
            $this->printCounts();
        } catch (Throwable $e) {
            $this->getDatabaseInternal()->rollBack();
            throw $e;
        }
        $this->getDatabaseInternal()->commit();
    }


    /**
     * Returns all article ids
     *
     * @return int[]
     */
    private function getArticleIds(): array
    {
        $sql        = "SELECT id FROM comms_news_article ORDER BY id";
        $articleIds = $this->getDatabaseInternal()->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $articleIds);
    }


    /**
     * Process a single article
     *
     * @param int $articleId
     */
    private function processArticle(int $articleId): void
    {
        $this->getLogger()->info("Processing article $articleId");
        $originalAudience    = $this->buildAudience($articleId);
        $noDuplicateChildren = $this->deleteDuplicateCompositeChildren($originalAudience);
        $this->deleteDuplicateRoots($noDuplicateChildren);
    }


    /**
     * Examines each root segment against other root segments and deletes duplicates.
     *
     * Assumes that composite children duplicates have already been removed
     *
     * @param News\Audience\Model[] $audience
     * @return News\Audience\Model[]
     */
    private function deleteDuplicateRoots(array $audience): array
    {
        $uniqueRootSegments = [];
        foreach ($audience as $segmentId => $rootSegment) {
            $duplicateFound = false;
            foreach ($uniqueRootSegments as $uniqueRootSegment) {
                if ($rootSegment->equals($uniqueRootSegment)) {
                    $duplicateFound = true;
                    break;
                }
            }
            if ($duplicateFound) {
                $rootSegment->isSimple() ? $this->countRootSimple++ : $this->countRootComposites++;
                $this->deleteSegment($rootSegment);
                unset($audience[$segmentId]);
            } else {
                $uniqueRootSegments[$segmentId] = $rootSegment;
            }
        }
        return $audience;
    }


    /**
     * Examines each composite segment and deletes any duplicate children in the composite
     *
     * This should do nothing, as a DB index prevents these sorts of duplicates occurring at a schema level.
     *
     * @param News\Audience\Model[] $audience
     * @return News\Audience\Model[]
     */
    private function deleteDuplicateCompositeChildren(array $audience): array
    {
        foreach ($audience as $segmentId => $rootSegment) {
            if ($rootSegment->isSimple()) {
                continue;
            }

            // Unique children for this composite audience
            $uniqueChildren = [];
            foreach ($rootSegment->getChildren() as $childSegment) {
                $duplicateFound = false;
                foreach ($uniqueChildren as $uniqueChild) {
                    if ($childSegment->equals($uniqueChild)) {
                        $duplicateFound = true;
                        break;
                    }
                }
                if ($duplicateFound) {
                    $this->countCompositeChildren++;
                    $this->deleteSegment($childSegment);
                    $rootSegment->getChildren()->offsetUnset($childSegment->getId());
                } else {
                    $uniqueChildren[$childSegment->getId()] = $childSegment;
                }
            }
        }

        return $audience;
    }


    /**
     * Hard deletes an audience segment model (leaf-first to respect FK constraints)
     *
     * @param News\Audience\Model $segment
     */
    private function deleteSegment(News\Audience\Model $segment): void
    {
        foreach ($segment->getChildren() ?? [] as $child) {
            $this->deleteSegment($child);
        }

        $this->getLogger()->warning(sprintf(
            "Deleting '%s' '%s' segment [id:%d, type:'%s', value:'%s', relationship:'%s']",
            $segment->isSimple() ? 'simple' : 'composite',
            $segment->getParent() ? 'child' : 'root',
            $segment->getId(),
            $segment->getEntityType(),
            $segment->getEntityValue() ?? 'NULL',
            $segment->getRelationship()
        ));
        $this->deleteSegmentById($segment->getId());
    }


    /**
     * Hard deletes an audience segment
     *
     * @param int $segmentId
     */
    private function deleteSegmentById(int $segmentId): void
    {
        $this->countTotal++;

        $sql = "DELETE FROM comms_news_audience WHERE id = :id";
        $this->getDatabaseInternal()->executeQuery($sql, ['id' => $segmentId]);
    }


    /**
     * Returns the audience for the specified article
     *
     * @param int $articleId
     * @return News\Audience\Model[] Indexed by audience.id
     */
    private function buildAudience(int $articleId): array
    {
        $sql = "
            SELECT 
                   cna1.id 'cna1.id',
                   cna1.entity_type 'cna1.entity_type',
                   cna1.entity_value 'cna1.entity_value',
                   cna1.relationship 'cna1.relationship',
                   cna2.id 'cna2.id',
                   cna2.parent_id 'cna2.parent_id',
                   cna2.entity_type 'cna2.entity_type',
                   cna2.entity_value 'cna2.entity_value',
                   cna2.relationship 'cna2.relationship'
            FROM comms_news_audience cna1 
            LEFT JOIN comms_news_audience cna2 ON cna2.parent_id = cna1.id
            WHERE cna1.comms_news_article_id = :article_id
        ";

        $statement = $this->getDatabaseInternal()->executeQuery($sql, ['article_id' => $articleId]);

        /** @var News\Audience\Model[] $rootSegments */
        $rootSegments = [];
        while ($row = $statement->fetch()) {
            $parentId = $row['cna1.id'];
            if (!array_key_exists($parentId, $rootSegments)) {
                $rootSegments[$parentId] = (new News\Audience\Model())
                    ->setId((int)$row['cna1.id'])
                    ->setEntityType($row['cna1.entity_type'])
                    ->setEntityValue($row['cna1.entity_value'])
                    ->setRelationship($row['cna1.relationship'])
                    ->setChildren(News\Audience\Model::createCollection());
            }
            $parent  = $rootSegments[$parentId];
            $childId = $row['cna2.id'];
            if ($childId) {
                $child = (new News\Audience\Model())
                    ->setId((int)$row['cna2.id'])
                    ->setParent($parent)
                    ->setEntityType($row['cna2.entity_type'])
                    ->setEntityValue($row['cna2.entity_value'])
                    ->setRelationship($row['cna2.relationship']);
                $parent->getChildren()->setOffset($childId, $child);
            }
        }
        return $rootSegments;
    }


    /**
     * Prints the results of the tool's execution
     */
    private function printCounts(): void
    {
        $log = $this->getLogger();

        $level = $this->countRootSimple ? Monolog\Logger::WARNING : Monolog\Logger::NOTICE;
        $log->log($level, "{$this->countRootSimple} simple root segments deleted");

        $level = $this->countRootComposites ? Monolog\Logger::WARNING : Monolog\Logger::NOTICE;
        $log->log($level, "{$this->countRootComposites} composite root segments deleted");

        $level = $this->countCompositeChildren ? Monolog\Logger::WARNING : Monolog\Logger::NOTICE;
        $log->log($level, "{$this->countCompositeChildren} composite's children deleted");

        $level = $this->countTotal ? Monolog\Logger::WARNING : Monolog\Logger::NOTICE;
        $log->log($level, "{$this->countTotal} total segments deleted");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
