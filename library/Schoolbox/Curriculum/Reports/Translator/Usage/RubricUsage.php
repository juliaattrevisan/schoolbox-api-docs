<?php

namespace Schoolbox\Curriculum\Reports\Translator\Usage;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\User;

class RubricUsage
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    public function __invoke(CapabilityModel $capability, array $usage): array
    {
        return $this->translate($capability, $usage);
    }

    private function translate(CapabilityModel $capability, array $usage): array
    {
        return [
            'name' => $capability->getName(),
            'indicators' => array_map(
                fn($indicator) => [
                    'name' => $indicator['name'],
                ],
                $usage
            )
        ];
    }
}
