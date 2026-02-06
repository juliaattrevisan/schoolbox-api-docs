<?php

namespace Schoolbox\CustomisationRecord\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Assessment\Participant\Model;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserModelToArray;

class CsvModelToArray
{
    public function __invoke(Model $participant): array
    {
        $plan = $participant->getPlan();
        $originalActivity = $plan->getAssessment()->getOriginalAssessment() ?? $plan->getAssessment();

        /** @var UnitImplModel $context */
        $context = $plan->getAssessment()->getContextUnitImplementation();
        if (null === $context) {
            /* Assessment in a project, then look at the project context
             */
            $context = $plan->getAssessment()->getContextProject()->getAssessmentModel()->getContextUnitImplementation();
        }

        return [
            'created_at' => $participant->getCreatedAt()->getShort(),
            'user' => implode(', ', [
                $participant->getUser()->getLastname(),
                $participant->getUser()->getGivenName()
            ]),
            'customised_activity' => $plan->getName() ?? $plan->getAssessment()->getTitle(),
            'original_activity' => $originalActivity->getTitle(),
            'type' => $plan->getCustomisationType(),
            'unit' => $context?->getTitle(),
            'class' => $context?->getFolder()?->getTitle()
        ];
    }
}
