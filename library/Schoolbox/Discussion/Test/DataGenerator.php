<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Test;

use Faker;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;
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
     * @var Thread\Context\Mapper
     */
    private $threadContextMapper;


    /**
     * @var Post\Mapper
     */
    private $postMapper;


    /**
     * Constructor
     *
     * @param Faker\Generator       $faker
     * @param UserMapper            $userMapper
     * @param News\Mapper           $articleMapper
     * @param Thread\Mapper         $threadMapper
     * @param Thread\Context\Mapper $threadContextMapper
     * @param Post\Mapper           $postMapper
     */
    public function __construct(
        Faker\Generator $faker,
        UserMapper $userMapper,
        News\Mapper $articleMapper,
        Thread\Mapper $threadMapper,
        Thread\Context\Mapper $threadContextMapper,
        Post\Mapper $postMapper
    ) {
        $this->faker               = $faker;
        $this->userMapper          = $userMapper;
        $this->articleMapper       = $articleMapper;
        $this->threadMapper        = $threadMapper;
        $this->threadContextMapper = $threadContextMapper;
        $this->postMapper          = $postMapper;
    }


    /**
     * Generate test data
     *
     * @param int|null $seed If provided, use this as the RNG seed
     * @throws \Schoolbox\Mapper\Exception if something went wrong?
     */
    public function generateTestData(int $seed = null): void
    {
        if (isset($seed)) {
            mt_srand($seed);
        }


        // Delete all the old threads and posts
        $this->threadMapper->delete($this->threadMapper->findAll());


        // Who may author a post?
        $authors = $this->userMapper->findAll()->getAll();


        // What comms articles are available?
        // Build thread + comments for, say, 75% of them
        $articleIds              = array_map('intval', $this->articleMapper->findAllIds());
        $articleIdsForDiscussion = $this->faker->unique()->randomElements(
            $articleIds,
            max(1, (int)(count($articleIds) * 0.75))
        );
        sort($articleIdsForDiscussion);


        // Build threads and posts for each article
        foreach ($articleIdsForDiscussion as $articleId) {
            // Build a single thread
            $thread = $this->threadMapper->createModel()
                ->setOpen(true);

            $context = $this->threadContextMapper->createModel()
                ->setThread($thread)
                ->setContextType(Thread\Context\Model::CONTEXT_COMMS)
                ->setContextId($articleId);

            // Build a bunch of comments
            // Need to be able to override created date for all of them though?
            $posts = $this->postMapper->createCollection();
            foreach (range(1, $this->faker->numberBetween(1, 100)) as $_) {
                $post = $this->postMapper->createModel()
                    ->setAuthor($this->faker->randomElement($authors))
                    ->setThread($thread)
                    ->setContent($this->randomArticle($this->faker->numberBetween(1, 4)));
                $posts->add($post);
            }

            $this->threadMapper->save($thread);
            $this->threadContextMapper->save($context);
            $this->postMapper->save($posts);
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
                function ($p) { return sprintf('<p>%s</p>', $p); },
                $this->faker->paragraphs($nb)
            )
        );
    }
}
