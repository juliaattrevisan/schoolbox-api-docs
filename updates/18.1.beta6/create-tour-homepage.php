<?php

/**
 * Create homepage feature tour
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Create Feature Tour',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $database = Database::getInstance();

    // Put all the variable details of the tour in... variables
    $tour_uuid        = 'f4bb1eb3-c5c2-46f0-853d-a8873f2d34ca';
    $tour_title       = 'Staff Homepage Tour';
    $tour_description = 'This tour will walk staff through the common tools for building a homepage';

    $steps = [
        // Introduction
        [
            'uuid'         => '89742c3d-0c88-407b-a005-a8873f2d34ca',
            'tooltip_uuid' => '52dfefce-5e6e-4c68-b2c3-a8873f2d34ca',
            'type'         => 'modal',
            'title'        => 'Setting up homepages',
            'content'      => "
                <p>You can arrange components on this homepage, you just need to know a few simple things before you get started.</p>
            ",
            'css_class'    => 'introjs-intromodal',
        ],

        // Unlock page
        [
            'uuid'         => '359bc5c5-ffc8-46be-a20c-a8873f2d34ca',
            'tooltip_uuid' => 'f1040b20-6014-44ab-8e88-a8873f2d34ca',
            'type'         => 'tooltip',
            'selector'     => '#component-lock-switch',
            'content'      => "
                <p>This switch will unlock all the components on the page, revealing extra buttons on the header. Once unlocked you can drag and drop components using the <i class='icon-grip'></i> handle. You can also adjust component settings using the <i class='icon-settings'></i> button.</p>
            ",
        ],

        // Add components
        [
            'uuid'         => '1a47bac6-c9e3-4c6f-979b-a8873f2d34ca',
            'tooltip_uuid' => '888a36a5-5c22-4e29-b959-a8873f2d34ca',
            'type'         => 'tooltip',
            'selector'     => '#add-component',
            'content'      => "<p>Here you will find a wide range of components to add extra features to your page.</p>",
        ],

        // Edit colours and layout
        [
            'uuid'         => '5d2ebff9-8a70-4d12-a46a-a8873f2d34ca',
            'tooltip_uuid' => '7da9259f-4e4b-4b35-8b4c-a8873f2d34ca',
            'type'         => 'tooltip',
            'selector'     => '#edit-column',
            'content'      => "<p>You can customise the layout and colours of your page via this button.</p>",
        ],
        // Actions
        [
            'uuid'         => '388c55bd-4dd4-4011-bc92-a8873f2d34ca',
            'tooltip_uuid' => 'ca753320-c576-416d-a32f-a8873f2d34ca',
            'type'         => 'tooltip',
            'selector'     => '#all-actions',
            'content'      => "<p>Looking for something else? Make sure you check the three dot menu for extra tools you can use from this page.</p>",
        ],

    ];


    // Insert the container into the tour table
    $statement = $database->prepare("
        INSERT INTO tour (
          `uuid`,
          `author_user_id`,
          `title`,
          `description`,
          `active`,
          `created_at`,
          `updated_at`,
          `deleted_at`
        ) VALUES (
          :uuid,
          (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
          :title,
          :description,
          1,
          NOW(),
          NOW(),
          NULL
        );
    ");
    $statement->execute([
        'uuid'        => $tour_uuid,
        'title'       => $tour_title,
        'description' => $tour_description,
    ]);


    // Insert the audience (all roles under the 'staff' role type)
    $statement = $database->prepare("
        INSERT INTO   `tour_audience_role`
        (
                      `moderator_user_id`,
                      `tour_uuid`,
                      `role_id`
        )
        SELECT        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                      :tour_uuid,
                      `id`
        FROM          `groups`
        WHERE         `group_type` = 'staff';
    ");
    $statement->execute([
        'tour_uuid' => $tour_uuid,
    ]);


    // Insert the trigger (on load of /)
    $statement = $database->prepare("
        INSERT INTO     `tour_trigger`
        (
                        `uuid`,
                        `author_user_id`,
                        `tour_uuid`,
                        `selector`,
                        `event`,
                        `url_regex`,
                        `created_at`,
                        `updated_at`,
                        `deleted_at`
        )
        SELECT          '9b7e1ebd-c51b-4e42-a323-a8873f2d34ca',
                        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                        :tour_uuid,
                        '#component-lock-switch',
                        'editTourStart',
                        '^/homepage/.*',
                        NOW(),
                        NOW(),
                        NULL
    ");
    $statement->execute([
        'tour_uuid' => $tour_uuid,
    ]);


    // Reverse the array so that we can insert the 'next_step_uuid' without getting an FK error
    $stepsReversed = array_reverse($steps);

    // Insert the content
    $i = 0;
    foreach ($stepsReversed as $step) {
        $statement = $database->prepare("
          INSERT INTO   `tour_step`
          (
                        `uuid`,
                        `tour_uuid`,
                        `next_step_uuid`,
                        `author_user_id`,
                        `type`,
                        `created_at`,
                        `updated_at`,
                        `deleted_at`
          )
          SELECT        :uuid,
                        :tour_uuid,
                        :next_step_uuid,
                        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                        :step_type,
                        NOW(),
                        NOW(),
                        NULL
        ");

        $next_step_uuid = null;

        if ($i !== 0) {
            $next_step_uuid = $stepsReversed[$i - 1]['uuid'];
        }

        $statement->execute([
            'uuid'           => $step['uuid'],
            'tour_uuid'      => $tour_uuid,
            'next_step_uuid' => $next_step_uuid,
            'step_type'      => $step['type'],
        ]);


        if ($step['type'] === 'tooltip') {
            $statement = $database->prepare("
              INSERT INTO   `tour_step_tooltip`
              (
                            `uuid`,
                            `tour_step_uuid`,
                            `body`,
                            `selector`,
                            `css_class`,
                            `created_at`,
                            `updated_at`,
                            `deleted_at`
              )
              SELECT        :uuid,
                            :tour_step_uuid,
                            :content,
                            :selector,
                            NULL,
                            NOW(),
                            NOW(),
                            NULL
            ");
            $statement->execute([
                'uuid'           => $step['tooltip_uuid'],
                'tour_step_uuid' => $step['uuid'],
                'content'        => trim($step['content']),
                'selector'       => $step['selector'],
            ]);
        } elseif ($step['type'] === 'modal') {
            $statement = $database->prepare("
              INSERT INTO   `tour_step_modal`
              (
                            `uuid`,
                            `tour_step_uuid`,
                            `title`,
                            `body`,
                            `css_class`,
                            `created_at`,
                            `updated_at`,
                            `deleted_at`
              )
              SELECT        :uuid,
                            :tour_step_uuid,
                            :title,
                            :content,
                            :css_class,
                            NOW(),
                            NOW(),
                            NULL
            ");
            $statement->execute([
                'uuid'           => $step['tooltip_uuid'],
                'tour_step_uuid' => $step['uuid'],
                'title'          => trim($step['title']),
                'content'        => trim($step['content']),
                'css_class'      => $step['css_class'],
            ]);
        }

        // Insert any step events
        if (isset($step['events'])) {
            foreach ($step['events'] as $event) {
                $statement = $database->prepare("
                  INSERT INTO   `tour_step_event`
                  (
                                `uuid`,
                                `tour_step_uuid`,
                                `selector`,
                                `event`,
                                `insertion_point`,
                                `order`,
                                `created_at`,
                                `updated_at`,
                                `deleted_at`
                  )
                  SELECT        :uuid,
                                :tour_step_uuid,
                                :selector,
                                :event,
                                :insertion_point,
                                :eventOrder,
                                NOW(),
                                NOW(),
                                NULL
                ");
                $statement->execute([
                    'uuid'            => $event['uuid'],
                    'tour_step_uuid'  => $step['uuid'],
                    'selector'        => $event['selector'],
                    'event'           => $event['event'],
                    'insertion_point' => $event['insertion_point'],
                    'eventOrder'      => $event['order'],
                ]);
            }
        }

        $i++;
    }


    // Update the tour table with the first step uuid
    $statement = $database->prepare("
        UPDATE          `tour`
        SET             `first_step_uuid` = :first_step_uuid
        WHERE           `uuid` = :uuid
    ");
    $statement->execute([
        'uuid'           => $tour_uuid,
        'first_step_uuid' => $steps[0]['uuid'],
    ]);
});
