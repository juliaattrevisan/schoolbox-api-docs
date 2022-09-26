<?php

namespace Schoolbox\Tour;

use Symfony\Component\Yaml\Yaml;
use Schoolbox\Application\CliTool;

/**
 * Used for importing tours from a YAML file on the CLI
 * See tools/tour/install.php for more information on how it is used
 * There is a sample.yaml file also in tools/tour/sample.yaml for information on the layout of the yaml structure
 *
 * @package Schoolbox\Tour
 */
class Import extends CliTool\App
{
    public function run(): void
    {
        global $argv;

        if (!isset($argv[1])) {
            $filename = basename(__FILE__);
            echo
            <<<HELP
Imports a tour written in YAML format

Usage: $filename [--perform] [FILE]

    --perform: use this flag to ensure changes are persisted, otherwise just shows what would happen

HELP
            ;
            exit;
        }

        $file = array_pop($argv);
        if (!(is_file($file) && is_readable($file))) {
            $this->getLogger()->error("File \"$file\" does not exist, or is not readable.");
            exit(1);
        }

        $tourSource = Yaml::parseFile($file);

        $tourSource = $tourSource['tour'];

        $tourID = $this->createTour($tourSource['title'], $tourSource['description']);
        $this->createAudience($tourID, $tourSource['roles']);
        $this->createTrigger($tourID, $tourSource['trigger']['urlRegex'], $tourSource['trigger']['selector'], $tourSource['trigger']['event']);

        $steps  = array_reverse($tourSource['steps']);
        $stepID = null;

        foreach ($steps as $step) {
            $step   = $step['step'];
            $stepID = $this->createStep($tourID, $stepID, $step['type'], $step['title'], $step['content'], $step['class'], $step['selector'] ?? null, $step['events'] ?? null);
        }

        if ($this->perform()) {
            // Update the tour table with the first step uuid
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
            UPDATE          `tour`
            SET             `first_step_uuid` = :first_step_uuid
            WHERE           `uuid` = :uuid
        "
            );
            $statement->execute(
                [
                    'uuid'            => $tourID,
                    'first_step_uuid' => $stepID,
                ]
            );
        }
    }

    /**
     *
     * @param string $title
     * @param string $description
     * @return string Tour GUID
     */
    private function createTour(string $title, string $description): string
    {
        $this->getLogger()->info('Creating a new tour called: ' . $title);

        $tourID = $this->generateGUID();

        if ($this->perform()) {
            $statement = $this->getDatabaseInternal()->prepareQuery(
                "
            INSERT INTO tour (
              `uuid`,
              `author_user_id`,
              `title`,
              `description`,
              `active`,
              `created_at`,
              `updated_at`
            ) VALUES (
              :uuid,
              (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
              :title,
              :description,
              1,
              NOW(),
              NOW()
            )"
            );
            $statement->execute(
                [
                    'uuid'        => $tourID,
                    'title'       => $title,
                    'description' => $description,
                ]
            );
        }
        return $tourID;
    }

    /**
     * @param string $tourID
     * @param array  $roleTypes
     */
    private function createAudience(string $tourID, array $roleTypes): void
    {
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
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
                WHERE         `group_type` = :roleType;"
        );

        foreach ($roleTypes as $type) {
            $this->getLogger()->info('Making tour visible for: ' . $type);
            if ($this->perform()) {
                $statement->execute(
                    [
                        'tour_uuid' => $tourID,
                        'roleType'  => $type,
                    ]
                );
            }
        }
    }

    /**
     * @param string $tourID
     * @param string $url
     * @param string $selector
     * @param string $event
     */
    private function createTrigger(string $tourID, string $url, string $selector, string $event): void
    {
        $this->getLogger()->info("Creating tour trigger pages '$url' with the selector '$selector' '$event' ");
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
        INSERT INTO     `tour_trigger`
        (
                        `uuid`,
                        `author_user_id`,
                        `tour_uuid`,
                        `selector`,
                        `event`,
                        `url_regex`,
                        `created_at`,
                        `updated_at`
        )
        VALUES (        :trigger_uuid,
                        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                        :tour_uuid,
                        :selector,
                        :event,
                        :url,
                        NOW(),
                        NOW()
                )"
        );
        if ($this->perform()) {
            $statement->execute(
                [
                    'trigger_uuid' => $this->generateGUID(),
                    'tour_uuid'    => $tourID,
                    'selector'     => $selector,
                    'event'        => $event,
                    'url'          => $url,
                ]
            );
        }
    }

    /**
     * @param string      $tourID
     * @param string|null $nextStepID
     * @param string      $type
     * @param string      $title
     * @param string      $body
     * @param string|null $class
     * @param string|null $selector
     * @param array|null  $events
     * @return string
     */
    private function createStep(string $tourID, ?string $nextStepID, string $type, string $title, string $body, ?string $class, ?string $selector, ?array $events): string
    {
        $this->getLogger()->info("Creating tour step of '$type'");
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
          INSERT INTO   `tour_step` (
                        `uuid`,
                        `tour_uuid`,
                        `next_step_uuid`,
                        `author_user_id`,
                        `type`,
                        `created_at`,
                        `updated_at`
          )
          VALUES (      :uuid,
                        :tour_uuid,
                        :next_step_uuid,
                        (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                        :step_type,
                        NOW(),
                        NOW()
                )"
        );

        $stepID = $this->generateGUID();

        if ($this->perform()) {
            $statement->execute(
                [
                    'uuid'           => $stepID,
                    'tour_uuid'      => $tourID,
                    'next_step_uuid' => $nextStepID,
                    'step_type'      => $type,
                ]
            );


            if ($type === 'tooltip') {
                $this->createTooltipStep($stepID, $body, $selector, $class);
            } elseif ($type === 'modal') {
                $this->createModalStep($stepID, $title, $body, $class);
            }

            // Insert any step events
            if (is_array($events)) {
                $i = 0;
                foreach ($events as $event) {
                    $event = $event['event'];
                    $this->createStepEvent($stepID, $event['selector'], $event['call'], $event['when'], $i++);
                }
            }
        }

        return $stepID;
    }

    /**
     * @param string $stepID
     * @param string $title
     * @param string $body
     * @param ?string $class
     */
    private function createModalStep(string $stepID, string $title, string $body, ?string $class): void
    {
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
              INSERT INTO   `tour_step_modal`
              (
                            `uuid`,
                            `tour_step_uuid`,
                            `title`,
                            `body`,
                            `css_class`,
                            `created_at`,
                            `updated_at`
              )
               VALUES (     :uuid,
                            :tour_step_uuid,
                            :title,
                            :content,
                            :css_class,
                            NOW(),
                            NOW()
                        ) "
        );
        $statement->execute(
            [
                'uuid'           => $this->generateGUID(),
                'tour_step_uuid' => $stepID,
                'title'          => trim($title),
                'content'        => trim($body),
                'css_class'      => $class,
            ]
        );
    }

    /**
     * @param string $stepID
     * @param string $body
     * @param string $selector
     * @param ?string $class
     */
    private function createTooltipStep(string $stepID, string $body, string $selector, ?string $class): void
    {
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
              INSERT INTO   `tour_step_tooltip`
              (
                            `uuid`,
                            `tour_step_uuid`,
                            `body`,
                            `selector`,
                            `css_class`,
                            `created_at`,
                            `updated_at`
              )
              SELECT        :uuid,
                            :tour_step_uuid,
                            :content,
                            :selector,
                            :css_class,
                            NOW(),
                            NOW()
            "
        );
        $statement->execute(
            [
                'uuid'           => $this->generateGUID(),
                'tour_step_uuid' => $stepID,
                'content'        => trim($body),
                'selector'       => $selector,
                'css_class'      => $class,
            ]
        );
    }

    /**
     * @param string $stepID
     * @param string $selector
     * @param string $event
     * @param string $when
     * @param int    $order
     */
    private function createStepEvent(string $stepID, string $selector, string $event, string $when, int $order): void
    {
        $statement = $this->getDatabaseInternal()->prepareQuery(
            "
                  INSERT INTO   `tour_step_event`
                  (
                                `uuid`,
                                `tour_step_uuid`,
                                `selector`,
                                `event`,
                                `insertion_point`,
                                `order`,
                                `created_at`,
                                `updated_at`
                  )
                  SELECT        :uuid,
                                :tour_step_uuid,
                                :selector,
                                :event,
                                :insertion_point,
                                :eventOrder,
                                NOW(),
                                NOW()
                "
        );
        $statement->execute(
            [
                'uuid'            => $this->generateGUID(),
                'tour_step_uuid'  => $stepID,
                'selector'        => $selector,
                'event'           => $event,
                'insertion_point' => $when,
                'eventOrder'      => $order,
            ]
        );
    }

    /**
     * @return string
     */
    private function generateGUID(): string
    {
        $uuid = md5(uniqid(mt_rand(), true));
        return substr($uuid, 0, 8) . "-" .
            substr($uuid, 8, 4) . "-" .
            substr($uuid, 12, 4) . "-" .
            substr($uuid, 16, 4) . "-" .
            substr($uuid, 20, 12);
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
}
