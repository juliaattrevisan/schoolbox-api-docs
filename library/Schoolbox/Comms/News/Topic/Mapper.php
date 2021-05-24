<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Topic;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping()
    {
        $this->hasTable('newsTopic');
        $this->hasModel(Model::class);

        $this->hasMap([
            'column'     => 'newsTopicID',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true,
        ]);

        $this->hasMap([
            'column'   => 'name',
            'property' => 'name',
            'type'     => Mapper::TYPE_STRING,
        ]);

        $this->hasMap([
            'column'   => 'slug',
            'property' => 'slug',
            'type'     => Mapper::TYPE_STRING,
        ]);

        $this->hasMap([
            'column'   => 'sequence',
            'property' => 'sequence',
            'type'     => Mapper::TYPE_STRING,
        ]);

        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'updateSlug']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeSlug']);
        $this->registerCallback(self::CALLBACK_PRERESTORE, [$this, 'updateSlug']);
    }


    /**
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }

    /**
     * @param Model $topic
     * @return bool
     */
    public function updateSlug(Model $topic): bool
    {
        // It won't appear in the UI
        if (
            empty($topic->getName()) ||
            $topic->getDeletedAt() && (!$topic->getDeletedAt()->isnull() || !($topic->getDeletedAt() instanceof \Alaress_Date_NullDate))
        ) {
            $topic->setSlug(null);
            return true;
        }

        $sql    = "SELECT slug FROM {$this->getTable()}
                   WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($topic->getId())) {
            $sql    .= " AND newsTopicID != :topicId";
            $params = ['topicId' => $topic->getId()];
        }
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        $slugs = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $url      = new \Url($topic->getName());
        $newSlug  = $url->getSlug();
        $original = $newSlug;
        $i        = 0;
        while (in_array($newSlug, $slugs)) {
            $i++;
            $newSlug = "{$original}-{$i}";
        }

        $topic->setSlug($newSlug);
        return true;
    }


    /**
     * @param Model $topic
     * @return bool
     */
    public function removeSlug(Model $topic): bool
    {
        $sql  = "UPDATE {$this->getTable()}
                   SET slug = NULL
                   WHERE newsTopicID = :topicId";
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['topicId' => $topic->getId()]);
        return true;
    }
}
