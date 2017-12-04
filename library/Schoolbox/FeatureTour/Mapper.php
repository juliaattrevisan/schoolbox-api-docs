<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\FeatureTour;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('tour');
        $this->hasModel(Model::class);

        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('active', self::TYPE_BOOLEAN);

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, function (Model $model) {
            return $this->preSave($model);
        });
    }


    /**
     * @param Model $model
     * @return bool
     */
    private function preSave(Model $model)
    {
        if ($model->getUuid()) {
            // already got an ID; do nothing

            // TODO consider validating that it's *actually* a UUID
            return true;
        }

        // TODO find a way to do this without the pre-query
        $model->setUuid($this->database->query('SELECT UUID()')->fetchColumn());

        return true;
    }
}
