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
 * A shortcut to simple operations that involve multiple steps
 *
 * Sometimes items are in an Archived state but its activities
 * are in a Published state, making the whole thing unable to
 * archive due to a bug in Learnosity.
 */
class Helper
{
    /**
     * The "published" state of an Activity/Item
     */
    public const LEARNOSITY_ITEM_STATUS_PUBLISHED = 'published';


    /**
     * The "unpublished" state of an Activity/Item
     */
    public const LEARNOSITY_ITEM_STATUS_UNPUBLISHED = 'unpublished';


    /**
     * The "archived" state of an Activity/Item
     *
     * Softdeleting is equivalent to Learnosity's "archiving"
     */
    public const LEARNOSITY_ITEM_STATUS_ARCHIVED = 'archived';

    /**
     * The configuration used for the regions to show in the Learnosity player
     *
     * Currently used for both Items API and Assess API initialisations but should be the same for both
     * Items: https://reference.learnosity.com/items-api/initialization/config.regions
     * Assess: https://reference.learnosity.com/assess-api/initialization/regions
     */
    public const LEARNOSITY_CONFIG_PLAYER_REGIONS = [
        'top-left' => [
            ['type' => 'title_element'],
        ],
        'top-right' => [
            ['type' => 'itemcount_element'],
            ['type' => 'reading_timer_element'],
            ['type' => 'timer_element'],
        ],
        'items' => [
            ['type' => 'progress_element'],
            ['type' => 'slider_element']
        ],
        'right' => [
            ['type' => 'save_button'],
            ['type' => 'reviewscreen_button'],
            ['type' => 'resource_button'],
            ['type' => 'flagitem_button'],
            ['type' => 'masking_button'],
            ['type' => 'verticaltoc_element'],
        ],
        'bottom-left' => [
            [
                'type' => 'previous_button',
                'show_label_option' => true
            ],
        ],
        'bottom-right' => [
            ['type' => 'next_button'],
        ]
    ];

    /**
     * Constructor.
     *
     * @param Api $api
     * @param LoggerInterface|null $logger
     */
    public function __construct(private Api $api, private ?LoggerInterface $logger = null)
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
        $this->changeState($learnosityReference, self::LEARNOSITY_ITEM_STATUS_ARCHIVED);
    }


    /**
     * Restore an Activity to Published state
     *
     * @param string $learnosityReference
     * @return void
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function restore(string $learnosityReference): void
    {
        $this->changeState($learnosityReference, self::LEARNOSITY_ITEM_STATUS_PUBLISHED);
    }


    /**
     * Change the state of an Activity and all its Items inside to $status
     *
     * @param string $learnosityReference
     * @param string $status
     * @return void
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function changeState(string $learnosityReference, string $status): void
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

        /* $activities['data'] is a one-element stack in this usage
         */
        foreach ($activities['data'] as $activity) {
            /* Note that sometimes you get an error message like this from Learnosity
             * due to a bug in their system: https://help.learnosity.com/hc/requests/23138
             */

            /* Shank the activity into the Archived state
             */
            $updatedActivity = $activity;
            $updatedActivity['status'] = $status;
            $activityPayload['activities'][] = $updatedActivity;

            $activityArchiveResult = $this->api->dataSetRequest('/itembank/activities', $activityPayload);

            /* Then shank its items into the $status state
             */
            $itemReferences = [];

            /* This quiz might not have any questions (items) in it
             */
            if (isset($activity['data']['items'])) {
                foreach ($activity['data']['items'] as $item) {
                    $itemReferences[] = $item['reference'];
                }
            }

            /* Get all the items' details so we can change their status
             * and all its related pieces
             *
             * Even thought he activity didn't have items in it,
             * there might be orphaned data in learnosity; we
             * can check anyway
             */
            $this->logger?->debug(sprintf(
                'Fetching items for activity %s',
                $activity['reference']
            ));

            /* No items? No trabajo
             */
            if (\count($itemReferences)) {
                /* Change status for all the items collated in $references
                 * If this is empty, it returns ALL ITEMS which isn't what you want.
                 */
                $items = $this->api->dataGetRequest('/itembank/items', ['references' => $itemReferences]);

                $itemsPayload = [
                    'items' => []
                ];

                foreach ($items['data'] as $item) {
                    $this->logger?->debug(sprintf(
                        'Setting item %s status to %s in payload',
                        $item['reference'],
                        $status
                    ));

                    $updatedItem = $item;
                    $updatedItem['status'] = $status;

                    $itemsPayload['items'][] = $updatedItem;
                }

                /* Shank them into the Archived state
                 */
                $this->logger?->debug(sprintf(
                    'Update items to status %s for activity %s',
                    $status,
                    $activity['reference']
                ));

                $itemArchiveResult = $this->api->dataSetRequest('/itembank/items', $itemsPayload);
            } else {
                $this->logger?->debug(sprintf(
                    'No items for activity %s; continuing onwards',
                    $activity['reference']
                ));

                return;
            }
        }
    }
}
