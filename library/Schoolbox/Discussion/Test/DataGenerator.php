<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Test;

use Faker;
use Psr\Log\LoggerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\UserMapper;

/**
 * Generate test data for testing discussion threads.
 *
 * Obviously, don't run this on a live server.
 */
class DataGenerator
{
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var Faker\Generator
     */
    private $faker;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var News\Mapper
     */
    private $articleMapper;


    /**
     * @var Thread\Mapper
     */
    private $threadMapper;


    /**
     * @var Comment\Mapper
     */
    private $commentMapper;


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param Faker\Generator $faker
     * @param UserMapper      $userMapper
     * @param News\Mapper     $articleMapper
     * @param Thread\Mapper   $threadMapper
     * @param Comment\Mapper  $commentMapper
     */
    public function __construct(
        LoggerInterface $log,
        Faker\Generator $faker,
        UserMapper $userMapper,
        News\Mapper $articleMapper,
        Thread\Mapper $threadMapper,
        Comment\Mapper $commentMapper
    ) {
        $this->log           = $log;
        $this->faker         = $faker;
        $this->userMapper    = $userMapper;
        $this->articleMapper = $articleMapper;
        $this->threadMapper  = $threadMapper;
        $this->commentMapper = $commentMapper;
    }


    /**
     * Generate test data
     *
     * @param int|null $seed If provided, use this as the RNG seed
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception if something went wrong?
     * @throws \UnexpectedValueException
     */
    public function generateTestData(int $seed = null): void
    {
        if (isset($seed)) {
            mt_srand($seed);
        }

        // Delete all the old threads and comments
        $this->log->notice('Soft-deleting all existing threads and comments');
        $this->threadMapper->delete($this->threadMapper->findAll());


        // Who may author a comment?
        $authors = $this->userMapper->findAll()->getAll();


        // What comms articles are available?
        // Build thread + comments for, say, 75% of them
        $articles              = $this->articleMapper->findAll(['order' => ['id' => 'asc']]);
        $articlesForDiscussion = $this->faker->unique()->randomElements(
            $articles->getAll(),
            max(1, (int)(count($articles) * 0.75))
        );


        // Build threads and comments for each article
        foreach ($articlesForDiscussion as $article) {
            $commentCount = $this->faker->numberBetween(1, 100);
            $this->log->notice(
                sprintf(
                    'Adding %d comments for article #%d (%s)',
                    $commentCount,
                    $article->getId(),
                    $article->getTitle()
                )
            );
            // Build a single thread
            $thread = $this->threadMapper->createModel()
                ->setOpen(true);

            // Build a bunch of comments
            // Need to be able to override created date for all of them though?
            $comments = $this->commentMapper->createCollection();
            foreach (range(1, $commentCount) as $_) {
                $comment = $this->commentMapper->createModel()
                    ->setAuthor($this->faker->randomElement($authors))
                    ->setThread($thread)
                    ->setContent($this->randomArticle($this->faker->numberBetween(1, 4)));
                $comments->add($comment);
            }

            $this->threadMapper->save($thread);
            $this->commentMapper->save($comments);


            // And add the thread to the comms article
            $article->setDiscussionThread($thread);
            $this->articleMapper->save($article);
        }
    }


    /**
     * Generate random article.
     *
     * @param int $nb How many paragraphs the article should be
     * @return string
     */
    private function randomArticle($nb = 3): string
    {
        return implode(
            '',
            array_map(
                function ($p) {
                    return sprintf('<p>%s</p>', $p);
                },
                $this->faker->paragraphs($nb)
            )
        );
    }
}
