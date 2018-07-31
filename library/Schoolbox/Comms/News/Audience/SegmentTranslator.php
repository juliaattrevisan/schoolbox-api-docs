<?php
/**
 * SegmentTranslator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience;

class SegmentTranslator
{
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
     * @var Mapper
     */
    private $audienceMapper;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * SegmentTranslator constructor.
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
     * @param string $entityType
     * @param string $entityValue
     * @param string $relationship
     * @return string
     */
    public function translate(string $entityType, string $entityValue, string $relationship): string
    {
        $string = "{$this->cache[$entityType][$entityValue]['name']} ({$this->entityTranslation[$entityType]})";

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
     * @return SegmentTranslator
     */
    public function buildCache(int $articleId): self
    {
        $statement = $this->audienceMapper->getDatabase()->prepare("
            (SELECT cna.*, luCampus.`name` AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN luCampus ON entity_type = 'campus' AND entity_value = luCampus.id
            WHERE comms_news_article_id = :articleId)
            
            UNION
            
            (SELECT cna.*, groups.name AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN groups ON entity_type = 'role' AND entity_value = groups.id
            WHERE comms_news_article_id = :articleId)
            
            UNION
            
            (SELECT cna.*, house.house AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN house ON entity_type = 'school_house' AND entity_value = house.id
            WHERE comms_news_article_id = :articleId)
            
            UNION
            
            SELECT cna.*, folder.`name` AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN folder ON entity_type = 'folder' AND entity_value = folder.id
            WHERE comms_news_article_id = :articleId
            
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
            WHERE comms_news_article_id = :articleId)
            
            UNION
            
            (SELECT cna.*, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = 'pastoral_group' AND entity_value = pastoral_group.id
            WHERE comms_news_article_id = :articleId)
        ");

        $statement->execute([
            'articleId' => $articleId,
        ]);

        /* Build associative array for quick lookup
         */
        $refined = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $refined[$row['entity_type']][$row['entity_value']]['name'] = $row['name'];
            $refined[$row['entity_type']][$row['entity_value']]['relationships'][] = $row['relationship'];
        }

        /* For each of the folders, get their class codes as well
         */
        if (array_key_exists('folder', $refined)) {
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
                    $refined['folder'][$row['id']]['name'] .= " {$row['codes']}";
                }
            }
        }

        $this->cache = $refined;

        return $this;
    }

    /**
     * @return array
     */
    public function getCache(): array
    {
        return $this->cache;
    }
}