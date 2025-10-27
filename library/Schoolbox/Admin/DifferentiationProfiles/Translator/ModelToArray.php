<?php

namespace Schoolbox\Admin\DifferentiationProfiles\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Admin\DifferentiationProfiles\Model;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
    ) {
    }

    public function __invoke(Model $model): array
    {
        return [
            'id'          => $model->getId(),
            'name'        => $model->getName(),
            'module'      => $model->getModule(),
            'title'       => $model->getTitle(),
            'terminology' => $model->getTerminology(),
            'description' => $model->getDescription(),
            'is_enabled'  => $model->getIsEnabled(),
            'sequence'    => $model->getSequence(),
        ];
    }
}
