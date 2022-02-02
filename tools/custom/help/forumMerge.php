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
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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
        /**
         * Delivered folder Array
         * release ID => folderId
         * @var array
         */
        $deliveredFolder = [
            '11.0'  => 319,
            '11.5'  => 381,
            '12.0'  => 382,
            '12.5'  => 383,
            '13.0'  => 384,
            '13.5'  => 385,
            '14.0'  => 386,
            '14.5'  => 387,
            '15.0'  => 388,
            '15.5'  => 389,
            '15.6'  => 570,
            '16.0'  => 993,
            '16.5'  => 1427,
            '17.0'  => 1792,
            '17.5'  => 2138,
            '18'   => 2239,
            '18.1' => 2385,
            '18.2' => 2448,
            '19'   => 2476,
            '19.2' => 2567,
            '20'   => 2686,
            '20.1' => 2968,
            '20.2' => 3050,
            '21'   => 3067,
            '21.1' => 3174,
        ];

        /**
         * Folders to move the Forums to
         * There Are Questions both Unanswered and Answered, Feature Requests
         */
        $newFolderForum = [
            'QIT'     => 2793,
            'QTL'     => 2794,
            'QC'      => 2795,
            'QO'      => 2798,
            'QITANS'  => 2886,
            'QTLANS'  => 2888,
            'QCANS'   => 2885,
            'QOANS'   => 2887,
            'FRO'     => 2871,
            'FRTL'    => 2867,
            'FRCOM'   => 2868,
            'FRIT'    => 2869,
            'FRUX'    => 2870
        ];


        $threadMapper = ThreadMapper::getInstance();
        $postMapper = PostMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();

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
        $predicate->like('title', '[%]%');
        $forums = $threadMapper->findByPredicates($predicate);

        $count = $forums->count();
        if ($count === 0) {
            $this->getLogger()->notice("No Forums found to merge");
            exit;
        }
        $this->getLogger()->info("Found {$count} Forum(s)");

        //loop through threads
        /** @var \Schoolbox\Forum\Thread $forum */
        foreach ($forums as $forum) {
            $title = $forum->getTitle();
            $this->getLogger()->info("Forum: {$title}");
            $actioned = false;
            //get the forum ID to merge into.
            $matches = '';
            preg_match("[MERGE (\d+)]", $forum->getTitle(), $matches);

            //if matched here merge thr forum into the new forum
            if (!empty($matches)) {
                $actioned = true;
                $this->getLogger()->info("Merge Forums based on [MERGE \d+]");
                $mergeId = $matches[1];

                $this->getLogger()->notice("Found Forum '{$title}' to merge into: Forum ID: {$mergeId}");

                //check if the merge ID has been exists. /else return a warning, and do nothing.
                //this does not do a full hydrate, so i think its fine to just ignore the returned data
                try {
                    $merge      = $threadMapper->findById($mergeId);
                    $mergeTitle = $merge->getTitle();
                    $this->getLogger()->notice("New Forum: {$mergeId} Title: '{$mergeTitle}'");
                } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                    $this->getLogger()->warning("Forum: {$mergeId} has been Deleted");
                    continue;
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->getLogger()->warning("Forum: {$mergeId} does not exist");
                    continue;
                }

                //move this threads posts to the merge ID (if perform, else just display the list)
                $postsToMerge = $postMapper->findByColumn('threadID', $forum->getId());
                $postCount    = $postsToMerge->count();
                if (!$this->perform()) {
                    $this->getLogger()->notice("Would have updated the following {$postCount} posts:");
                }
                foreach ($postsToMerge as $post) {
                    $postId = $post->getId();
                    $this->getLogger()->notice("Post: {$postId} to merge into: Forum: {$mergeId}");
                    if ($this->perform()) {
                        $post->setThread($mergeId);
                        $postMapper->save($post);
                    }
                }

                //'set deleted' on the current thread (if perform)
                if (!$this->perform()) {
                    $this->getLogger()->notice("Would have Soft Deleted Forum: '{$title}'");
                }
                if ($this->perform()) {
                    $this->getLogger()->notice("Soft Deleting Forum: '{$title}'");
                    $forum->setDeleted(true);
                    $threadMapper->save($forum);
                }
            }

            //clear $matches
            $matches = '';
            preg_match("[DELIVERED (\d+\.\d)]", $forum->getTitle(), $matches);
            //if matched here merge the forum into the new folder
            if (!empty($matches) && false ===  $actioned) {
                $actioned = true;
                $this->getLogger()->info("Merge Forums based on [DELIVERED \d+\.\d]");
                $mergeId = $matches[1];
                $newFolder = $deliveredFolder[$mergeId];
                $this->getLogger()->notice("Found Forum '{$title}' to move into folder {$newFolder}");
                $forum->setCategory($folderMapper->findCheaplyById($newFolder));
                $forum->setLocked(true);
                if ($this->perform()) {
                    $this->getLogger()->notice("Moved Forum '{$title}' into folder {$newFolder}, and locked it");
                    $threadMapper->save($forum);
                }
            }


            //clear $matches
            $matches = '';
            preg_match("[(\w+)]", $forum->getTitle(), $matches);
            //if matched here merge the forum into the new folder
            if (!empty($matches) && false ===  $actioned) {
                $actioned = true;
                if ("RESOLVED" === $matches[0] || 'Resolved' === $matches[0]) {
                    /**
                     * Don't try to move forums with the title '[RESOLVED]'
                     * PST will need to fix this, as we don't know where they need to go.
                     */
                    $this->getLogger()->warning("Unknown [Resolved] forum title, Please update it to [Delivered xx.x]
or to the correct Question Folder. For Forum: {$forum->getID()} then run this again.");
                    continue;
                }
                if ("DELIVERED" === $matches[0]) {
                    /**
                     * Historic 'Delivered v20.0 and before' folder place here please
                     */
                    $this->getLogger()->notice("Found Forum '{$title}' to move into folder 2881");
                    $forum->setCategory($folderMapper->findCheaplyById('2881'));
                    $forum->setLocked(true);
                    if ($this->perform()) {
                        $this->getLogger()->notice("Moved Forum '{$title}' into folder 2881, and locked it");
                        $threadMapper->save($forum);
                    }
                    continue;
                }
                if ("ANSWERED" === $matches[0]) {
                    /**
                     * Don't try to move forums with the title '[ANSWERED]'
                     * PST will need to fix this, as we don't know where they need to go.
                     */
                    $this->getLogger()->warning("Unknown ANSWERED forum title, Please update it to the correct question folder. For Forum: {$forum->getID()} then run this again.");
                    continue;
                }
                $this->getLogger()->info("Merge Forums based on [\w+]");
                $mergeId = $matches[1];
                $newFolder = $newFolderForum[$mergeId];
                $this->getLogger()->notice("Found Forum '{$title}' to move into folder {$newFolder}");
                $forum->setCategory($folderMapper->findCheaplyById($newFolder));
                if ($this->perform()) {
                    $this->getLogger()->notice("Moved Forum '{$title}' into folder {$newFolder}");
                    $threadMapper->save($forum);
                }
            }
        }
        $this->getLogger()->info("All Complete.");
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
