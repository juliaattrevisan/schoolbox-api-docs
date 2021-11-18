#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Forum\PostMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
$app = new class extends CliTool\App {
// --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return false;
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Merges Forum threads with '[MERGE id:\d+]' in the title into the thread identified by 'merge :id'

Usage: forumMerge.php [-v] [-p]
        -p Actually merge, else just display what would occur
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        $threadMapper = ThreadMapper::getInstance();
        $postMapper = PostMapper::getInstance();

        if (!$this->perform()) {
            $this->getLogger()->alert("Preview Mode");
        } else {
            $this->getLogger()->alert("Action Mode");
        }

        /**
         * get list of all forum threads with [MERGE :id]
         * then merge the included posts to the :id forum thread
         * */

        //get list of forum threads with "[MERGE :id]" if the thread is not deleted
        $predicate = $threadMapper->getPredicateBuilder();
        $predicate->like('title', '[MERGE%');
        $forums = $threadMapper->findByPredicates($predicate);

        $count = $forums->count();
        if ($count === 0) {
            $this->getLogger()->notice("No Forums found to merge");
            exit;
        }
        $this->getLogger()->info("Found ${count} Forum(s)");

        //loop through threads
        foreach ($forums as $forum) {
            //get the forum ID to merge into.
            $matches = '';
            $title = $forum->getTitle();
            preg_match("[MERGE (\d+)]", $forum->getTitle(), $matches);

            //if no matched continue
            if (empty($matches)) {
                $this->getLogger()->warning("Forum '${title}' does not match [MERGE \d+]");
                continue;
            }

            $mergeId = $matches[1];

            $this->getLogger()->notice("Found Forum '${title}' to merge into: Forum ID: ${mergeId}");

            //check if the merge ID has been exists. /else return a warning, and do nothing.
            //this does not do a full hydrate, so i think its fine to just ignore the returned data
            try {
                $merge = $threadMapper->findById($mergeId);
                $mergeTitle = $merge->getTitle();
                $this->getLogger()->notice("New Forum: ${mergeId} Title: '${mergeTitle}'");
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                $this->getLogger()->warning("Forum: ${mergeId} has been Deleted");
                continue;
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->getLogger()->warning("Forum: ${mergeId} does not exist");
                continue;
            }

            //move this threads posts to the merge ID (if perform, else just display the list)
            $postsToMerge = $postMapper->findByColumn('threadID', $forum->getId());
            $postCount = $postsToMerge->count();
            if (!$this->perform()) {
                $this->getLogger()->notice("Would have updated the following ${postCount} posts:");
            }
            foreach ($postsToMerge as $post) {
                $postId = $post->getId();
                $this->getLogger()->notice("Post: ${postId} to merge into: Forum: ${mergeId}");
                if ($this->perform()) {
                    $post->setThread($mergeId);
                    $postMapper->save($post);
                }
            }

            //'set deleted' on the current thread (if perform)
            if (!$this->perform()) {
                $this->getLogger()->notice("Would have Soft Deleted Forum: '${title}'");
            }
            if ($this->perform()) {
                $this->getLogger()->notice("Soft Deleting Forum: '${title}'");
                $forum->setDeleted(true);
                $threadMapper->save($forum);
            }
        }
        $this->getLogger()->info("All Complete.");
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
