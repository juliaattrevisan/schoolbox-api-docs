<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Web\Controller\Base;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Filter;

class Controller extends Base
{
    /**
     * Controller constructor
     */
    public function __construct(
        private Mapper $mapper,
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

        $translator = new Translator\ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            new \Schoolbox\User\Translator\ModelToArray(),
            $this->assessmentMapperProxy
        );

        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            Filter\Page\Model::PAGE_CUSTOMISATION_RECORD,
            null,
            $this->getUser(),
            [],
            $this->requestQuery->getArrayCopy(),
        );

        $request = $this->requestQuery['filter_selection'] ?? [];
        if (!is_array($request)) {
            $this->sendJsonError('Invalid filter selection format.', 400);
            return;
        }

        $page  = Filter\Page\Model::PAGE_CUSTOMISATION_RECORD;
        $codec = new Filter\Codec($this->filterMapper, $this->filterSelectionMapper);

        $limit = (int)$this->requestQuery->get('limit', $this->mapper::DEFAULT_LIMIT);

        // Convert the request into a set of selections, and update them in the frontend
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Convert the set of selections into actual filters
        $dynamicFilters = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $this->getUser(),
            $page,
        );

        /** @var Filter\Filters\CustomisationRecord\Finder $finder */
        $finder = Filter\Filters\CustomisationRecord\Finder::getInstance($this->filterMapper, $this->mapper);

        $orderBy = $this->requestQuery->get('sortBy', Cursor::CURSOR_DEFAULT_ORDER_BY);
        $order = strtolower($this->requestQuery->get('order', $this->mapper::SORT_ORDER_ASC));

        $sortOrder = match ($orderBy) {
            Cursor::CURSOR_ORDER_BY_CREATED_AT => [
                'created_at' => $order,
                'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_CLASS_NAME => [
              'f.name' => $order,
              'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_UNIT_NAME => [
              'cui.title' => $order,
              'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_CUSTOMISATION_TYPE => [
                'pp.customisation_type' => $order,
                'id' => $order,
            ],
            /* Default to sorting by user's name
             */
            default => [
                'u.lastname'    => $order,
                'u.chosen_name' => $order,
                'id'  => $order,
            ]
        };

        $cursor = new Cursor(
            $this->getUser(),
            $finder,
            $dynamicFilters,
            $orderBy,
            $sortOrder,
            $limit,
        );

        $records = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);

        $data = $records->map(fn($record) => call_user_func($translator, $record))->reindex();

        $recordPayload = [
            'data' => $data,
            /* temporary cursor implementation
             */
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    "current" => $cursor->current($records),
                    "next" => $cursor->next($records),
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
                'records' => $recordPayload,
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
        $translator = new Translator\ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            new \Schoolbox\User\Translator\ModelToArray(),
            $this->assessmentMapperProxy
        );

        $request = $this->requestQuery['filter_selection'] ?? [];
        if (!is_array($request)) {
            $this->sendJsonError('Invalid filter selection format.', 400);
            return;
        }

        $page  = Filter\Page\Model::PAGE_CUSTOMISATION_RECORD;
        $codec = new Filter\Codec($this->filterMapper, $this->filterSelectionMapper);

        $limit = (int)$this->requestQuery->get('limit', $this->mapper::DEFAULT_LIMIT);

        // Convert the request into a set of selections, and update them in the frontend
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Convert the set of selections into actual filters
        $dynamicFilters = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $this->getUser(),
            $page,
        );

        /** @var Filter\Filters\CustomisationRecord\Finder $finder */
        $finder = Filter\Filters\CustomisationRecord\Finder::getInstance($this->filterMapper, $this->mapper);

        $orderBy = $this->requestQuery->get('sortBy', Cursor::CURSOR_DEFAULT_ORDER_BY);
        $order = strtolower($this->requestQuery->get('order', $this->mapper::SORT_ORDER_ASC));

        $sortOrder = match ($orderBy) {
            Cursor::CURSOR_ORDER_BY_CREATED_AT => [
                Cursor::CURSOR_ORDER_BY_CREATED_AT_COLUMN => $order,
                'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_CLASS_NAME => [
                Cursor::CURSOR_ORDER_BY_CLASS_NAME_COLUMN => $order,
                'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_UNIT_NAME => [
                Cursor::CURSOR_ORDER_BY_UNIT_NAME_COLUMN => $order,
                'id' => $order,
            ],
            Cursor::CURSOR_ORDER_BY_CUSTOMISATION_TYPE => [
                Cursor::CURSOR_ORDER_BY_CUSTOMISATION_TYPE_COLUMN => $order,
                'id' => $order,
            ],
            /* Default to sorting by user's name
             */
            default => [
                'u.lastname'    => $order,
                'u.chosen_name' => $order,
                'id'  => $order,
            ]
        };

        $cursor = new Cursor(
            $this->getUser(),
            $finder,
            $dynamicFilters,
            $orderBy,
            $sortOrder,
            $limit,
        );

        $records = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);

        $data = $records->map(fn($record) => call_user_func($translator, $record))->reindex();

        $recordPayload = [
            'data' => $data,
            /* temporary cursor implementation
             */
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    "current" => $cursor->current($records),
                    "next" => $cursor->next($records),
                ]
            ],
        ];
        $this->response = $this->responseAsJson($recordPayload);
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
