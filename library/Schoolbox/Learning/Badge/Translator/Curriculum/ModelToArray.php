<?php

namespace Schoolbox\Learning\Badge\Translator\Curriculum;

use Schoolbox\Learning\Badge\Model;

class ModelToArray
{
    /**
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id'        => $model->getId(),
            'title'     => $model->getName(),
            'category'  => $model->getCategory()->getName(),
            'yearLevel' => $model->getYearLevels() ? $model->getYearLevels()->getPropertyValues('name') : []
        ];
    }
}
