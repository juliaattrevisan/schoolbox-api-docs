<?php
/**
 * Transactor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use PDOException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Mapper;
use Schoolbox\Mapper\Exception as MapperException;

class Transactor
{

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
    public function load(News\Model $article): array
    {
        $pieces = [];

        $translator = (new Translator($this->audienceMapper->getDatabase()))->buildCache($article->getId());
        $audiencing = $translator->getCache();

        foreach ($audiencing as $entityType => $values) {
            foreach ($values as $value => $data) {
                foreach ($data['relationships'] as $relationship) {

                    /* translate "name" into something nicer to read for humans
                     */
                    $pieces[] = [
                        'id' => Codec::encode($entityType, $value, $relationship),
                        'name' => $translator->translateToSegment(
                            $entityType,
                            $value,
                            $relationship
                        ),
                    ];
                }
            }
        }

        return $pieces;
    }


    /**
     * Save the audiencing segments for a given article
     *
     * @param News\Model  $article
     * @param string $segments
     * @throws MapperException
     * @throws PDOException
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
                $segment = Codec::decode($audiencingOption);

                $audienceModel = $this->audienceMapper->createModel()
                    ->setCommsNewsArticle($article)
                    ->setEntityType($segment['entityType'])
                    ->setEntityValue($segment['entityValue'])
                    ->setRelationship($segment['relationship']);

                $this->audienceMapper->save($audienceModel);
            }
        }
    }
}