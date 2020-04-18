#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';


$app = new class extends CliTool\App {
    /** Hands off execution over to the App */
    public function run(): void
    {
        $this->getDatabaseInternal()->executeQuery("
                ALTER TABLE newsTopic ADD COLUMN slug VARCHAR(150) AFTER `name`,
                ADD UNIQUE INDEX idx_newsTopic_slug (slug)"
        );

        $topics = $this->getDatabaseInternal()->executeQuery(
            'SELECT `name`, `newsTopicID` FROM newsTopic'
        )->fetchAll();

        foreach ($topics as $topic) {
            $url = new \Url($topic['name']);
            try {
                $this->getDatabaseInternal()->executeQuery(
                    'UPDATE newsTopic SET slug = :slug WHERE newsTopicID = :topicId'
                    , ['slug' => $url->getSlug(), 'topicId' => $topic['newsTopicID']]);
            } catch (Exception $e) {
                $this->getLogger()->warning("Could not set slug for new topic: {$topic['newsTopicID']}: " . $e->getMessage());
            }
        }
    }
};
/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
