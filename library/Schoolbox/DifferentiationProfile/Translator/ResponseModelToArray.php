<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Schoolbox\DifferentiationProfile\Model;

class ResponseModelToArray
{
    public function __invoke(Model $model): array
    {
        return [
            'id' => $model->getId(),
            'updated_at' => $model->getUpdatedAt()->getShort(),
        ];
    }
}
