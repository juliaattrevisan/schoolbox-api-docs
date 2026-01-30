<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Participant\Mapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;

class Controller extends Base
{
    /**
     * Controller constructor
     */
    public function __construct(
        private Mapper $participantMapper,
        private MapperProxy $assessmentMapperProxy,
    ) {
        // Controller initialization code can go here
    }

    public function viewRecords(): void
    {
        $pageTitle = 'Customisation Records';
        $records = $this->participantMapper->findAllCustomisationRecords();

        $translator = new Translator\ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            new \Schoolbox\User\Translator\ModelToArray(),
            $this->assessmentMapperProxy
        );

        $records = $records->map(fn($record) => call_user_func($translator, $record));

//        new \Alaress_Probe($records); // For debugging purposes

        // Logic to view customisation records goes here
        $this->response = $this->responseFromTemplate('@customisationRecord/list.twig', [
            'allBreadcrumbs' => [
                [$pageTitle],
            ],
            'records' => $records,
            $pageTitle,
        ]);
    }
}
