<?php
/**
 * SegmentTranslator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Comms\News\Audience\Segment\Strategy\Campus;
use Schoolbox\Comms\News\Audience\Segment\TranslatorRegistry;


class SegmentTranslator
{
    /**
     * Translate entity types into something humanreadable
     *
     * @var array
     */
    private $entityTranslation;

    /**
     * Translate parents_of and teachers_of into something humanreadable
     *
     * @var array
     */
    private $modifierTranslation;

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
            'Parents of students',
            'Teachers of students'
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
        $translator = new TranslatorRegistry($this->audienceMapper->getDatabase());

        $statement = $this->audienceMapper->getDatabase()->prepare($translator->getTranslateSql());

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