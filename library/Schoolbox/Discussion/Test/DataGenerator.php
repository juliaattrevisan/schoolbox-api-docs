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




        // Build comms articles
        $articleMapper = News\Mapper::getInstance();
        foreach (range(1, random_int(5, 15)) as $count) {

            $article = $articleMapper->createModel()
                ->setTitle($this->faker->words(random_int(2, 5), true))
                // randomHtml needs the content of its <body> tag stripped out
                ->setBody($this->randomArticle())
            ;

            var_dump($article);
        }




        // Clear out all existing comms articles

        // Clear out all existing users? No, just add some new ones

        // Add some possible audiences for comms articles (and add users to them):
        // * Campus
        // * Classes/Groups
        // * Roles
        // * Houses
        // * Year levels

        // Create some comms articles, for a random audience each time
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
