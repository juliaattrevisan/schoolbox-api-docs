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
     * @param string $entityType
     * @param string $value
     * @param string $relationship
     * @return string
     */
    private function translate(string $entityType, string $value, string $relationship): string
    {
        $string = "{$value} ({$this->entityTranslation[$entityType]})";

        if ($relationship !== Model::RELATIONSHIP_SELF) {
            $string .= " [{$this->modifierTranslation[$relationship]}]";
        }

        return $string;
    }


    /**
     * Load the audiencing segments for a given article
     *
     * @param News\Model $article
     * @return array
     */
    public function load(News\Model $article): array
    {
        $audiencing = $this->audienceMapper->findByColumn('comms_news_article_id', $article->getId());

        $pieces = [];

        foreach ($audiencing as $audience) {
            $segment = $audience->getEntityType() . self::AUDIENCE_SEPARATOR . base64_encode($audience->getEntityValue());

            if ($audience->getRelationship() !== Model::RELATIONSHIP_SELF) {
                $segment .= self::AUDIENCE_SEPARATOR . $audience->getRelationship();
            }

            /* translate "name" into something nicer to read for humans
             */
            $pieces[] = [
                'id' => $segment,
                'name' => $this->translate($audience->getEntityType(), $audience->getEntityValue(), $audience->getRelationship()),
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