<?php
/**
 * Segments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Comms\News;
use Schoolbox\Mapper\Exception as MapperException;

class Segments
{

    /**
     * Separates the audiencing option type from its value
     * e.g. house:12345, role:5678
     */
    private const AUDIENCE_SEPARATOR = ':';

    /**
     * @var Mapper
     */
    private $audienceMapper;

    /**
     * Translate entity types into something humanreadable
     *
     * @var array
     */
    private $entityTranslation = [];

    /**
     * Translate parents_of and teachers_of into something humanreadable
     *
     * @var array
     */
    private $modifierTranslation = [];

    /**
     * Segments constructor.
     * @param Mapper $audienceMapper
     */
    public function __construct(Mapper $audienceMapper)
    {
        $this->audienceMapper = $audienceMapper;

        /* Entity names
         */
        $this->entityTranslation = array_combine(Model::getSimpleEntityTypes(), [
            'User',
            'Superuser',
            'Campus',
            'Role',
            'Role Type',
            'House',
            'Residential House',
            'Year Level',
            'Service',
            'Pastoral Group',
            'Folder'
        ]);

        /* Relationship modifiers
         */
        $this->modifierTranslation = array_combine(Model::getRelationships(), [
            'Self',
            'Parents',
            'Teachers'
        ]);
    }


    /**
     * Take a schematic entity type such as school_house and decide what a human would see in the ui
     * Yes, this is presentation logic, but backend is the cleanest place to put it for now due to other data sources
     * like ajax requests needing a translation service in a single point of control
     *
     * Return a string like "User (230) [Parents of]"
     *
     * @param array  $tc Translation Cache associative array of (id => nice words) tuples
     * @param string $entityType
     * @param string $value
     * @param string $relationship
     * @return string
     */
    private function translate(array $tc, string $entityType, string $value, string $relationship): string
    {
        $string = "{$tc[$entityType][$value]} ({$this->entityTranslation[$entityType]})";

        if ($relationship !== Model::RELATIONSHIP_SELF) {
            $string .= " [{$this->modifierTranslation[$relationship]}]";
        }

        return $string;
    }


    /**
     * Translation cache
     * All the audiencing segments and their human-readable names are collected here
     * There are six types: campus role house year folder pastoralgroup
     *
     * We asked dadt for permission, don't worry
     *
     * @param int $articleId
     * @return array
     */
    private function translationCache(int $articleId): array
    {
        $statement = $this->audienceMapper->getDatabase()->prepare("
            (SELECT cna.*, luCampus.`name` AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN luCampus ON entity_type = 'campus' AND entity_value = luCampus.id
            WHERE comms_news_article_id = :articleId_0)
            
            UNION
            
            (SELECT cna.*, groups.name AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN groups ON entity_type = 'role' AND entity_value = groups.id
            WHERE comms_news_article_id = :articleId_1)
            
            UNION
            
            (SELECT cna.*, house.house AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN house ON entity_type = 'school_house' AND entity_value = house.id
            WHERE comms_news_article_id = :articleId_2)
            
            UNION
            
            SELECT cna.*, folder.`name` AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN folder ON entity_type = 'folder' AND entity_value = folder.id
            WHERE comms_news_article_id = :articleId_3
            
            UNION
            
            (SELECT cna.*,
                CASE
                WHEN CONCAT('', 1*`user`.year) = `user`.year
                THEN CONCAT('Year ', `user`.year)
                ELSE `user`.year
                END
            AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN `user` ON entity_type = 'year' AND entity_value = `user`.year
            WHERE comms_news_article_id = :articleId_4)
            
            UNION
            
            (SELECT cna.*, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = 'pastoral_group' AND entity_value = pastoral_group.id
            WHERE comms_news_article_id = :articleId_5)
        ");

        $statement->execute([
            'articleId_0' => $articleId,
            'articleId_1' => $articleId,
            'articleId_2' => $articleId,
            'articleId_3' => $articleId,
            'articleId_4' => $articleId,
            'articleId_5' => $articleId,
        ]);

        /* Build associative array for quick lookup
         */
        $refined = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $refined[$row['entity_type']][$row['entity_value']] = $row['name'];
        }

        /* For each of the folders, get their class codes as well
         */
        $folders = array_keys($refined['folder']);

        if (\count($folders)) {
            $statement = $this->audienceMapper->getDatabase()->prepare("
                SELECT folder.id, CONCAT('(', GROUP_CONCAT(code SEPARATOR ', '), ')') AS codes
                FROM folder
                LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
                WHERE FIND_IN_SET(id, :folderIds)
                GROUP BY folder_code.folder_id
            ");

            $statement->execute([
                'folderIds' => implode(',', $folders)
            ]);

            /* Augment array with folder codes
             */
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $refined['folder'][$row['id']] .= " {$row['codes']}";
            }
        }

        return $refined;
    }


    /**
     * Load the audiencing segments for a given article
     *
     * @param News\Model $article
     * @return array
     */
    public function load(News\Model $article): array
    {

        /* TODO: I can probably remove this and use $tc instead...in a crappier sort of way
         */
        $audiencing = $this->audienceMapper->findByColumn('comms_news_article_id', $article->getId());

        $pieces = [];

        $tc = $this->translationCache($article->getId());

        foreach ($audiencing as $audience) {
            $segment = $audience->getEntityType() . self::AUDIENCE_SEPARATOR . base64_encode($audience->getEntityValue());

            if ($audience->getRelationship() !== Model::RELATIONSHIP_SELF) {
                $segment .= self::AUDIENCE_SEPARATOR . $audience->getRelationship();
            }

            /* translate "name" into something nicer to read for humans
             */
            $pieces[] = [
                'id' => $segment,
                'name' => $this->translate(
                    $tc,
                    $audience->getEntityType(),
                    $audience->getEntityValue(),
                    $audience->getRelationship()
                ),
            ];
        }

        return $pieces;
    }


    /**
     * Save the audiencing segments for a given article
     *
     * @param News\Model  $article
     * @param string $segments
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function save(News\Model $article, string $segments): void
    {

        /* Purge existing records before resaving the new ones
         */
        $this->audienceMapper->deleteByColumn('comms_news_article_id', $article->getId());

        if ('' !== $segments) {
            $audiencingOptions = explode(',', $segments);

            foreach ($audiencingOptions as $audiencingOption) {

                /* explode again on the separator
                 */
                $pieces = explode(self::AUDIENCE_SEPARATOR, $audiencingOption);

                $audienceModel = $this->audienceMapper->createModel()
                    ->setCommsNewsArticle($article)
                    ->setEntityType($pieces[0])
                    ->setEntityValue(base64_decode($pieces[1]));

                /* This has a Parents or Teachers modifier if the audiencing segment has 3 pieces
                 */
                $modifierCount = 3;
                $piecesCount   = \count($pieces);

                if ($piecesCount === $modifierCount) {
                    if ($pieces[$piecesCount - 1] === Model::RELATIONSHIP_PARENTS_OF) {
                        $audienceModel->setRelationship(Model::RELATIONSHIP_PARENTS_OF);
                    } elseif ($pieces[$piecesCount - 1] === Model::RELATIONSHIP_TEACHERS_OF) {
                        $audienceModel->setRelationship(Model::RELATIONSHIP_TEACHERS_OF);
                    }
                } else {
                    $audienceModel->setRelationship(Model::RELATIONSHIP_SELF);
                }

                $this->audienceMapper->save($audienceModel);
            }
        }
    }
}