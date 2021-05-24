#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';


$app = new class extends CliTool\App {
    /* @var array */
    private $slugs = [];

    /** Hands off execution over to the App */
    public function run(): void
    {
        $this->getDatabaseInternal()->executeQuery("
                ALTER TABLE newsTopic ADD COLUMN slug VARCHAR(150) DEFAULT NULL AFTER `name`,
                ADD UNIQUE INDEX idx_newsTopic_slug (slug)");

        $topics = $this->getDatabaseInternal()->executeQuery(
            'SELECT `name`, `newsTopicID` FROM newsTopic WHERE deleted_at IS NULL'
        )->fetchAll();

        foreach ($topics as $topic) {
            try {
                $slug = $this->generateSlug($topic['name']);
                $this->getDatabaseInternal()->executeQuery(
                    'UPDATE newsTopic SET slug = :slug WHERE newsTopicID = :topicId',
                    ['slug' => $slug, 'topicId' => $topic['newsTopicID']]
                );
            } catch (Exception $e) {
                $this->getLogger()->warning("Could not set slug for new topic: {$topic['newsTopicID']}: " . $e->getMessage());
            }
        }
    }

    /**
     * @param string|null $name
     * @return string|null
     */
    private function generateSlug(?string $name): ?string
    {
        // It won't appear in the UI
        if (empty($name)) {
            return null;
        }
        $url      = new \Url($name);
        $slug     = $url->getSlug();
        $i        = 0;
        $original = $slug;
        while (in_array($slug, $this->slugs)) {
            $i++;
            $slug = "{$original}-{$i}";
        }

        $this->slugs[] = $slug;
        return $slug;
    }
};
/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
