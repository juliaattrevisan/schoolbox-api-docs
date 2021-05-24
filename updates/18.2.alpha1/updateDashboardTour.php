<?php

/**
 * Update dashboard feature tour
 *
 * These adjustments were required because of the side menu stash feature
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Update dashboard feature tour',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $database = Database::getInstance();

    $tour_uuid = 'c0e11cc2-f402-11e7-9a86-08002771865f';

    $steps = [
        // Introduction
        [
            'uuid'         => '158389f5-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '2530b35a-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'modal',
            'title'        => 'Welcome to your dashboard!',
            'content'      => "
                <p>Take a quick tour to discover the main features available when you first log in. On this tour you will learn about the dashboard, navigation and search features.</p>
            ",
            'css_class'    => 'introjs-intromodal',
        ],

        // Customisable Links
        [
            'uuid'         => '3470d234-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '3c7c730a-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#side-menu',
            'content'      => "<p>On the left hand side of the page you will find your side menu.</p><p>The links found here are customised by your school and will help you navigate to commonly used pages.</p>",
        ],

        // My Subjects
        [
            'uuid'         => '42517dab-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '40ff9951-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#side-menu-mysubjects',
            'content'      => "<p>This section will display links to the classes you teach. These are preloaded from enrolment data set up by your system administrators.</p>",
        ],

        // My Groups
        [
            'uuid'         => '3b62f95b-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '45bd274b-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#side-menu-mygroups',
            'content'      => "<p>In this section, you will find links to groups that you have been added to or have voluntarily joined.</p>",
        ],

        // My Links
        [
            'uuid'         => '4674f77e-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '49d02613-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#side-menu-mylinks',
            'content'      => "<p>This section can be customised by you. By clicking on the pencil icon, you can add your own individual links that are important for you.</p>",
        ],

        // Hide side menu
        [
            'uuid'         => '6af2306a-f14f-11e8-8eb2-08002751fd3c',
            'tooltip_uuid' => '7f5035ca-f14f-11e8-8eb2-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#drawer',
            'content'      => "<p>If you would like more screen space, you can collapse the side panel using this button.</p>",
        ],

        // Top Nav
        [
            'uuid'         => '4a73e905-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '4f508e88-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#hybrid-bar',
            'content'      => "<p>The top menu contains major features and functions that you will need to frequently access. You can hover over the links to get more detail about each link.</p>",
        ],

        // Search
        [
            'uuid'         => '4f99c8fb-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '543e6d76-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#search',
            'content'      => "<p>In the top right hand corner you will see a search icon. Hovering over this reveals a search bar. You can search for users, page content, files, folders, links, news and events.</p>",
        ],

        // Account details
        [
            'uuid'         => '204e36bb-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '2c5fbc48-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#profile-drop',
            'content'      => "
                <p>Next to the search, you will see your profile picture. Clicking the profile image will give you some further options.</p>
            ",
        ],

        // Profile dropdown
        [
            'uuid'         => 'b3b2f865-f697-11e7-965e-08002751fd3c',
            'tooltip_uuid' => '321b5fd4-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#profile-options',
            'content'      => "<p>
                    <b>Profile</b> will allow you to view your profile.
                    <br />
                    <b>Settings</b> will allow you to customise your notifications.
                    <br />
                    <b>Logout</b> will log you out of the system.
                </p>",
            'events'       => [
                [
                    'uuid'            => '4225f5bf-ff01-11e7-9951-08002751fd3c',
                    'selector'        => '#account-content',
                    'event'           => 'dropdown.open',
                    'insertion_point' => 'open',
                    'order'           => 0,
                ],
            ],
        ],

        // Switch user
        [
            'uuid'         => '292d8471-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '36c20038-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#switch-user',
            'content'      => "<p>The switch user section will list your other profiles within the system. It allows you to move easily between multiple accounts.</p>",
        ],

        // Notifications
        [
            'uuid'         => '539d418c-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '58170527-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#notification-toggle',
            'content'      => "<p>The bell or notifications menu on the right of the screen is used to view your instant notifications. There are many notifications you can receive such as when a user posts in a social stream on a page you are involved in or if you have been added to a group page. Text in bold denotes a link that can be clicked. The notifications found here are customised by you and your school.</p>",
        ],

        // Dashboard
        [
            'uuid'         => '56fb3efc-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '5c2af8ec-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#content',
            'content'      => "<p>The central area of your screen is your dashboard.  This has been setup to provide you with important information and features from around the school.</p>",
        ],
    ];

    // Delete all the current steps in the tour for a fresh slate
    $database->query("SET FOREIGN_KEY_CHECKS=0");
    $statement = $database->prepare("
        DELETE `tour_step`, `tst`, `tse`, `tsm` FROM `tour_step` 
        LEFT JOIN tour_step_event tse on tour_step.uuid = tse.tour_step_uuid
        LEFT JOIN tour_step_modal tsm on tour_step.uuid = tsm.tour_step_uuid
        LEFT JOIN tour_step_tooltip tst on tour_step.uuid = tst.tour_step_uuid
        WHERE tour_uuid = :tour_uuid");
    $statement->execute([':tour_uuid' => $tour_uuid]);
    $database->query("SET FOREIGN_KEY_CHECKS=1");


    // Reverse the step array so that we can insert the 'next_step_uuid' without getting an FK error
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
        } else if ($step['type'] === 'modal') {
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
