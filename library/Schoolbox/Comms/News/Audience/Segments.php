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
     * Segments constructor.
     * @param Mapper $audienceMapper
     */
    public function __construct(Mapper $audienceMapper)
    {
        $this->audienceMapper = $audienceMapper;
    }


    /**
     * Load the audiencing segments for a given article
     *
     * @param News\Model $article
     * @return array
     */
    public function load(News\Model $article)
    {
        $audiencing = $this->audienceMapper->findByColumn('comms_news_article_id', $article->getId());

        $pieces = [];

        foreach ($audiencing as $audience) {
            $segment = $audience->getEntityType() . self::AUDIENCE_SEPARATOR . $audience->getEntityValue();

            if ($audience->getRelationship() !== Model::RELATIONSHIP_SELF) {
                $segment .= self::AUDIENCE_SEPARATOR . $audience->getRelationship();
            }

            /* TODO: translate "name" into something nicer to read for humans
             */
            $pieces[] = [
                'id' => $segment,
                'name' => $segment
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
            $piecesCount = \count($pieces);

            if ($piecesCount === $modifierCount) {
                if ($pieces[$piecesCount-1] === Model::RELATIONSHIP_PARENTS_OF) {
                    $audienceModel->setRelationship(Model::RELATIONSHIP_PARENTS_OF);
                } elseif ($pieces[$piecesCount-1] === Model::RELATIONSHIP_TEACHERS_OF) {
                    $audienceModel->setRelationship(Model::RELATIONSHIP_TEACHERS_OF);
                }
            } else {
                $audienceModel->setRelationship(Model::RELATIONSHIP_SELF);
            }

            $this->audienceMapper->save($audienceModel);
        }
    }
}