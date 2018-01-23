<?php
/**
 * Create dashboard feature tour
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Create Course Instances', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $database = Database::getInstance();

    // Put all the variable details of the tour in... variables
    $tour_uuid        = 'c0e11cc2-f402-11e7-9a86-08002771865f';
    $tour_title       = 'Staff Dashboard Tour';
    $tour_description = 'This tour will walk staff through the common layout elements and dashboard';

    $steps = [
        // Introduction
        [
            'uuid'         => '158389f5-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '2530b35a-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'modal',
            'content'      => "
                <h3>Welcome to your dashboard!</h3>
                <p>This tour will guide you through an overview of the main areas of the first screen you see when you log in. This includes; the dashboard, side menu and top menu.</p>
            ",
        ],

        // Account details
        [
            'uuid'         => '204e36bb-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '2c5fbc48-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#profile-accordion',
            'content'      => "
                <p>At the top of the side menu you will see your profile picture and name. Clicking the arrow will give you some further options.</p>
            ",
        ],

        // Profile accordion menu
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
                    'selector'        => '#account-menu',
                    'event'           => 'accordion.open',
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
            'content'      => "This section can be customised by you. By clicking on the pencil icon, you can add your own individual links that are important for you.",
        ],

        // Top Nav
        [
            'uuid'         => '4a73e905-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '4f508e88-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#top-menu',
            'content'      => "The top menu will have links set up by your school to make it easy to navigate to features or pages in the system. You can hover over the links to get more detail about each link.",
        ],

        // Search
        [
            'uuid'         => '4f99c8fb-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '543e6d76-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#search',
            'content'      => "In the top right hand corner you will see a search icon. Hovering over this reveals a search bar. You can search for users, page content, files, folders, links, news and events.",
        ],

        // Notifications
        [
            'uuid'         => '539d418c-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '58170527-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#notification-toggle',
            'content'      => "The bell or notifications menu on the right of the screen is used to view your instant notifications. There are many notifications you can receive such as when a user posts in a social stream on a page you are involved in or if you have been added to a group page. Text in bold denotes a link that can be clicked. The notifications found here are customised by you and your school.",
        ],

        // Dashboard
        [
            'uuid'         => '56fb3efc-f5b9-11e7-9503-08002751fd3c',
            'tooltip_uuid' => '5c2af8ec-ff4a-11e7-bd23-08002751fd3c',
            'type'         => 'tooltip',
            'selector'     => '#content',
            'content'      => "The larger main area of the screen is your dashboard. It has components added to it that will be uniquely chosen for you by your school. These components could have links to external content, commonly used pages, features specific to your role, or important news.",
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
        SELECT          'f5ffc078-ff48-11e7-bd23-08002751fd3c',
                        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                        :tour_uuid,
                        'body',
                        'tourReady',
                        '^/$',
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
                        'tooltip',
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
        ]);


        if ($step['type'] === 'tooltip') {

            $statement = $database->prepare("
              INSERT INTO   `tour_step_tooltip`
              (
                            `uuid`,
                            `tour_step_uuid`,
                            `author_user_id`,
                            `title`,
                            `body`,
                            `selector`,
                            `css_class`,
                            `created_at`,
                            `updated_at`,
                            `deleted_at`
              )
              SELECT        :uuid,
                            :tour_step_uuid,
                            (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                            NULL,
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

            // TODO with SA-5699, when infrastructure for modal step types exist - insert the content

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
        SET             `tour_step_uuid` = :tour_step_uuid
        WHERE           `uuid` = :uuid
    ");
    $statement->execute([
        'uuid'           => $tour_uuid,
        'tour_step_uuid' => $steps[0]['uuid'],
    ]);
});
