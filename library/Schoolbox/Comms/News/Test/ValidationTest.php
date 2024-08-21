<?php

/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Audience\MockUserResolver;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Validation\ValidArticleAttributes;
use Schoolbox\Comms\News\ValidDateOffset;
use Schoolbox\User;

class ValidationTest extends TestCase
{
    /**
     * Data provider for testValidModerationRequirements
     *
     * @return array
     */
    public function provider_validBroadcastRequirements(): array
    {
        $user = (new User())->setId(19);
        $date = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 14);
        $publishAt = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 15);
        $expiresAt = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 16);

        return [
            // Moderation required, an acceptor is known
            // News article can reach the masses
            'Moderation required, both acceptor details set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setSubmittedAt($date)
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt)
                    ->setAcceptedBy($user)
                    ->setAcceptedAt($date),
                true
            ],

            // Moderation required, an acceptor is known, but no date is available for some reason
            // News article cannot reach the masses
            'Moderation required, acceptor user set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt)
                    ->setAcceptedBy($user),
                false
            ],

            // Moderation required, an acceptor is known, but no acceptor is available for some reason
            // News article cannot reach the masses
            'Moderation required, acceptor date set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt)
                    ->setAcceptedBy(null)
                    ->setAcceptedAt($date),
                false
            ],

            // Acceptor must be set when moderation is required
            // When absent, the article isn't accepted; news article mustn't reach the masses
            'Moderation required, acceptor not set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt),
                false
            ],

            // Preserve last known acceptor in the scenario where moderation was previously required
            // at the time, but moderation is no longer required now. News article can reach the masses.
            'Moderation not required, acceptor set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setSubmittedAt($date)
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt)
                    ->setAcceptedBy($user)
                    ->setAcceptedAt($date),
                true
            ],

            // Edge case that no longer exists; when moderation isn't required, authors are their own
            // acceptor. Otherwise, someone else is the acceptor. In either situation this shouldn't arise
            'Moderation not required, acceptor not set' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt),
                false
            ],
        ];
    }

    /**
     * @param Model $article
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validArticle')]
    public function testValidArticle(Model $article, $expected): void
    {
        $validation = new ValidArticleAttributes();

        $this->assertEquals($expected, $validation->isSatisfiedBy($article));
    }

    /**
     * Data provider for testValidArticle
     *
     * @return array
     */
    public static function provider_validArticle(): array
    {
        $publishAt = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 15);
        $expiresAt = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 16);

        return [
            'Heading is set, both dates provided' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt),
                true
            ],
            'Publish date is missing' => [
                (new Model())
                    ->setTitle('cat')
                    ->setExpiresAt($expiresAt),
                false
            ],
            'Expiry date is missing' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($publishAt),
                false
            ],
            'Heading is missing 1' => [
                (new Model())
                    ->setPublishAt($publishAt)
                    ->setExpiresAt($expiresAt),
                false
            ],
            'Heading is missing 2' => [
                (new Model())
                    ->setExpiresAt($expiresAt),
                false
            ],
            'Heading is missing 3' => [
                (new Model())
                    ->setPublishAt($publishAt),
                false
            ],
        ];
    }


    /**
     * @param Model $article
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validArticleDates')]
    public function testValidArticleDates(Model $article, $expected): void
    {
        $validation = new ValidArticleAttributes();

        $this->assertEquals($expected, $validation->isSatisfiedBy($article));
    }

    /**
     * Data provider for testValidArticle
     *
     * @return array
     */
    public static function provider_validArticleDates(): array
    {
        $date1 = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 15);
        $date2 = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 16);

        return [
            'Publish occurs before Expiry' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($date1)
                    ->setExpiresAt($date2),
                true
            ],

            // "Publish Immediately" and/or "Expire Immediately" actions for moderators
            'Publish occurs simultaneously with Expiry' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($date1)
                    ->setExpiresAt($date1),
                true
            ],
            'Expiry occurs before Publish' => [
                (new Model())
                    ->setTitle('cat')
                    ->setPublishAt($date2)
                    ->setExpiresAt($date1),
                false
            ],
        ];
    }


    /**
     * @param string $dateOffset
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validDateOffset')]
    public function testValidDateOffset(string $dateOffset, $expected): void
    {
        $validation = new ValidDateOffset();

        $this->assertEquals($expected, $validation->isSatisfiedBy($dateOffset));
    }

    /**
     * Data provider for testValidDateOffset
     *
     * @return array
     */
    public static function provider_validDateOffset(): array
    {
        return [
            'Valid (+1 day)' => [
                '+1 day',
                true
            ],
            'Valid (+1 week)' => [
                '+1 week',
                true
            ],
            'Valid (+1 month)' => [
                '+1 month',
                true
            ],
            'Valid (tomorrow)' => [
                'tomorrow',
                true
            ],
            'Invalid (empty string)' => [
                '',
                false
            ],
            'Invalid (whitespace)' => [
                '   ',
                false
            ],
            'Invalid 1' => [
                'no particular time',
                false
            ],
            'Invalid 2' => [
                'later',
                false
            ],
            'Invalid 3' => [
                'a few days later',
                false
            ],
            'Invalid (trash 1)' => [
                'ALKJFLj297245u2o34jklhlsdf',
                false
            ],
            'Invalid (trash 2)' => [
                '@#(O$%&U@(*$U(@JKO$HJO@#H$(*@#YU( 9284 2894 y9284 y928y97&$(!@&$',
                false
            ],
        ];
    }


    /**
     * Data provider for testValidAccessibility
     *
     * @return array
     */
    public function provider_validAccessibility(): array
    {
        $userResolver = new MockUserResolver();

        $user3 = (new User())->setId(3);
        $user12345 = (new User())->setId(12345);

        /* Published, but not expired, article
         *
         * Submitted is set
         * Accepted date is set
         * Publish date in the past
         * Expiry date in the future
         * User is in audience
         */
        $articleValid1 = (new Model())
            ->setSubmittedAt(\Alaress_Date_Format::now()->addDays(-10))
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Published and expired article
         *
         * Submitted is set
         * Accepted date is set
         * Publish date in the past
         * Expiry date in the past
         * User is in audience
         */
        $articleValid2 = (new Model())
            ->setSubmittedAt(\Alaress_Date_Format::now()->addDays(-10))
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Submitted is NOT set
         * Accepted date is set
         * Publish date in the past
         * Expiry date in the future
         * User is in audience
         */
        $article1 = (new Model())
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Submitted is NOT set
         * Accepted date is NOT set
         * Publish date in the past
         * Expiry date in the future
         * User is in audience
         */
        $article2 = (new Model())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Submitted is set
         * Accepted date is set
         * Publish date in the future
         * Expiry date in the future
         * User is in audience
         */
        $article3 = (new Model())
            ->setSubmittedAt(\Alaress_Date_Format::now()->addDays(-10))
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Published, but not expired, article
         *
         * Submitted is set
         * Accepted date is set
         * Publish date in the past
         * Expiry date in the future
         * User is NOT in audience
         */
        $article4 = (new Model())
            ->setSubmittedAt(\Alaress_Date_Format::now()->addDays(-10))
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        /* Published and expired article
         *
         * Submitted is set
         * Accepted date is set
         * Publish date in the past
         * Expiry date in the past
         * User is NOT in audience
         */
        $article5 = (new Model())
            ->setSubmittedAt(\Alaress_Date_Format::now()->addDays(-10))
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1))
            ->setExpiresAt(\Alaress_Date_Format::now()->addDays(2));

        return [
            'Valid 1' => [
                $user3, $articleValid1, $userResolver::ARTICLE_AUDIENCES[2],
                true
            ],
            'Valid 2' => [
                $user3, $articleValid2, $userResolver::ARTICLE_AUDIENCES[2],
                true
            ],
            'Invalid 1' => [
                $user3, $article1, $userResolver::ARTICLE_AUDIENCES[2],
                false
            ],
            'Invalid 2' => [
                $user3, $article2, $userResolver::ARTICLE_AUDIENCES[2],
                false
            ],
            'Invalid 3' => [
                $user3, $article3, $userResolver::ARTICLE_AUDIENCES[2],
                false
            ],
            'Invalid 4' => [
                $user12345, $article4, $userResolver::ARTICLE_AUDIENCES[2],
                false
            ],
            'Invalid 5' => [
                $user12345, $article5, $userResolver::ARTICLE_AUDIENCES[2],
                false
            ]
        ];
    }
}
