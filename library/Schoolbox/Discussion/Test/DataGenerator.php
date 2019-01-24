<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Test;

use Alaress\Database\Connector\IConnector;
use Faker;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Post;
use Schoolbox\Discussion\Thread;
use Schoolbox\Role;
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
     * @var IConnector
     */
    private $connector;


    /**
     * Constructor
     *
     * @param Faker\Generator $faker
     * @param IConnector      $connector
     */
    public function __construct(Faker\Generator $faker, IConnector $connector)
    {
        $this->faker     = $faker;
        $this->connector = $connector;
    }


    /**
     * Generate test data
     *
     * @param int|null $seed If provided, use this as the RNG seed
     */
    public function generateTestData(int $seed = null): void
    {
        if (isset($seed)) {
            mt_srand($seed);
        }


        // Mappers (TODO: inject)
        $userMapper          = UserMapper::getInstance();
        $articleMapper       = News\Mapper::getInstance();
        $threadMapper        = Thread\Mapper::getInstance();
        $threadContextMapper = Thread\Context\Mapper::getInstance();
        $postMapper          = Post\Mapper::getInstance();


        // Delete all the old threads and posts
        $threadMapper->delete($threadMapper->findAll());



        // What comms articles are available?
        // Build thread + comments for, say, 75% of them
        $articleIds = array_map('intval', $articleMapper->findAllIds());
        $authors    = $userMapper->findAll()->getAll();
//        $authors    = UserMapper::getInstance()->findByColumnList('g.group_type', Role::STAFF)->getAll();


        $articleIdsForDiscussion = $this->faker->unique()->randomElements(
            $articleIds,
            max(1, (int)(count($articleIds) * 0.75))
        );
        sort($articleIdsForDiscussion);


        foreach ($articleIdsForDiscussion as $articleId) {
            // Build a single thread
            $thread = $threadMapper->createModel()
                ->setOpen(true);

            $context = $threadContextMapper->createModel()
                ->setThread($thread)
                ->setContextType(Thread\Context\Model::CONTEXT_COMMS)
                ->setContextId($articleId);

            // Build a bunch of comments
            // Need to be able to override created date for all of them though?
            $posts        = $postMapper->createCollection();
            foreach (range(1, $this->faker->numberBetween(1, 100)) as $_) {
                $post = $postMapper->createModel()
                    ->setAuthor($this->faker->randomElement($authors))
                    ->setThread($thread)
                    ->setContent($this->randomArticle($this->faker->numberBetween(1, 4)));
                $posts->add($post);
            }

            $threadMapper->save($thread);
            $threadContextMapper->save($context);
            $postMapper->save($posts);
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
