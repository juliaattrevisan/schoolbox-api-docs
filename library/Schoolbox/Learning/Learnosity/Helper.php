<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use LearnositySdk\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * A shortcut to simple operations that involve
 * multiple steps
 */
class Helper
{
    /**
     * Softdeleting is equivalent to Learnosity's "archiving"
     */
    private const LEARNOSITY_ITEM_STATUS_ARCHIVED = 'archived';


    /**
     * Constructor.
     *
     * @param Api $api
     * @param LoggerInterface|null $logger
     */
    public function __construct(private Api $api, private ?LoggerInterface $logger)
    {
    }


    /**
     * Archive (soft-delete) a particular Learnosity Activity
     * Activities must have at least one published item before an activity can be archived
     *
     * If you can't delete an activity because it tells you that you need a published item,
     * also check any Welcome/Exit screens - those are Items too
     *
     * @param string $learnosityReference
     * @return void
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function archive(string $learnosityReference): void
    {
        /* List of Items
         */
        $activities = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ]);

        /* Find all the items associated with the activity
         */
        $activityPayload = [
            'activities' => []
        ];

        foreach ($activities['data'] as $activity) {
            /* Shank the activity into the Archived state
             */
            $updatedActivity = $activity;
            $updatedActivity['status'] = self::LEARNOSITY_ITEM_STATUS_ARCHIVED;
            $activityPayload['activities'][] = $updatedActivity;

            $activityArchiveResult = $this->api->dataSetRequest('/itembank/activities', $activityPayload);

            /* Then shank its items into the Archived state
             */
            $references = [];

            /* This quiz might not have any questions (items) in it
             */
            if (isset($activity['data']['items'])) {
                foreach ($activity['data']['items'] as $item) {
                    $references[] = $item['reference'];
                }
            }

            /* Get all the items' details so we can archive it
             * and all its related pieces
             *
             * Even thought he activity didn't have items in it,
             * there might be orphaned data in learnosity; we
             * can check anyway
             */
            $this->logger?->debug(sprintf(
                'Fetching items for activity %s',
                $updatedActivity['reference']
            ));

            /* No items? No trabajo
             */
            if (!\count($references)) {
                $this->logger?->debug(sprintf(
                    'No items for activity %s; continuing onwards',
                    $updatedActivity['reference']
                ));

                return;
            }

            /* Archive all the items collated in $references
             * If this is empty, it returns ALL ITEMS which isn't what you want.
             */
            $items = $this->api->dataGetRequest('/itembank/items', ['references' => $references]);

            $itemsPayload = [
                'items' => []
            ];

            foreach ($items['data'] as $item) {
                $this->logger?->debug(sprintf(
                    'Setting item %s status to archived',
                    $item['reference']
                ));

                $updatedItem = $item;
                $updatedItem['status'] = self::LEARNOSITY_ITEM_STATUS_ARCHIVED;

                $itemsPayload['items'][] = $updatedItem;
            }

            /* Shank them into the Archived state
             */
            $this->logger?->debug(sprintf(
                'Archiving items for activity %s',
                $updatedActivity['reference']
            ));

            $itemArchiveResult = $this->api->dataSetRequest('/itembank/items', $itemsPayload);
        }
    }
}
