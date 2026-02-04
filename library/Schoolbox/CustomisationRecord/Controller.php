<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Participant\Mapper;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;

class Controller extends Base
{
    /**
     * Controller constructor
     */
    public function __construct(
        private Mapper $participantMapper,
        private MapperProxy $assessmentMapperProxy,
        private IFactoryFactory $filterFactoryFactory,
        private \Schoolbox\Filter\Mapper $filterMapper,
        private \Schoolbox\Filter\Selection\Mapper $filterSelectionMapper,
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

        $route = $this->getRouteIfAuthorized('customisationRecord.getListRecords');
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            \Schoolbox\Filter\Page\Model::PAGE_CUSTOMISATION_RECORD,
            null,
            $this->getUser(),
            [],
            $this->requestQuery->getArrayCopy(),
        );

        $records = $records->map(fn($record) => call_user_func($translator, $record));

        $records = [
            'data' => $records,
            /* temporary cursor implementation
             */
            'metadata' => [
                'count' => count($records),
                'cursor' => [
                    "current" => null,
                    "next" => null,
                ]
            ],
        ];

        // Logic to view customisation records goes here
        $this->response = $this->responseFromTemplate(
            '@customisationRecord/list.twig',
            [
                'allBreadcrumbs' => [
                    [$pageTitle],
                ],
                'header' => $this->recordTableHeaders(),
                'records' => $records,
                'routes' => [
                    'getData' => $this->getRouteIfAuthorized('customisationRecord.getListRecords'),
                ],
                'filterPanel' => $filterPanel,
            ],
            $pageTitle,
        );
    }

    public function getListRecords(): void
    {
        $records = $this->participantMapper->findAllCustomisationRecords();

        $translator = new Translator\ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            new \Schoolbox\User\Translator\ModelToArray(),
            $this->assessmentMapperProxy
        );

        $records = $records->map(fn($record) => call_user_func($translator, $record));

        $records = [
            'data' => $records->reindex(),
            /* temporary cursor implementation
             */
            'metadata' => [
                'count' => count($records),
                'cursor' => [
                    "current" => null,
                    "next" => null,
                ]
            ],
        ];
        $this->response = $this->responseAsJson($records);
    }

    private function recordTableHeaders(): array
    {
        return [
            [
                'key' => 'user',
                'title' => 'Name',
                'type' => 'User',
                'isSortable' => true,
            ],
            [
                'key' => 'created_at',
                'title' => 'Date (Created)',
                'type' => 'DateTime',
                'isSortable' => true,
            ],
            [
                'key' => 'class',
                'title' => 'Class',
                'type' => 'Basic',
                'isSortable' => true,
            ],
            [
                'key' => 'unit',
                'title' => 'Unit',
                'type' => 'Basic',
                'isSortable' => true,
            ],
            [
                'key' => 'type',
                'title' => 'Customisation Type',
                'type' => 'Basic',
                'isSortable' => true,
            ],
            [
                'key' => 'customised_activity',
                'title' => 'Customised Activity',
                'type' => 'Basic',
                'isSortable' => false,
            ],
            [
                'key' => 'original_activity',
                'title' => 'Original Activity',
                'type' => 'Basic',
                'isSortable' => false,
            ],
        ];
    }
}
