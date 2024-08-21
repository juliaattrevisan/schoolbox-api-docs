<?php

/**
 * ValidHasReadMoreTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidHasReadMore;

/**
 * Class ValidHasReadMoreTest
 *
 * @package Schoolbox\Comms\News\Test
 */
class ValidHasReadMoreTest extends TestCase
{

    /**
     * @param Model  $article
     * @param string $readMore
     * @param bool   $commentsAllowed
     * @param bool   $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidHasReadMore')]
    public function testValidHasReadmore(Model $article, string $readMore, bool $commentsAllowed, bool $expected)
    {

        $outcome = (new ValidHasReadMore())->isSatisfiedBy($article, $readMore, $commentsAllowed);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidHasReadMore
     *
     * @return array
     */
    public static function providerValidHasReadMore(): array
    {

        /* Empty article, avoid of everything
         */
        $emptyArticle = (new Model());

        /* Article with a title only
         */
        $titleOnlyArticle = (new Model())
            ->setTitle('Just a title');

        /* Article with a blurb only
         */
        $blurbOnlyArticle = (new Model())
            ->setBlurb("I learned that everyone's got a sacred story when you take the time to listen. And I heard Reverend Jeremiah A. So don't tell me that Democrats won't defend this country. They have fought in our wars, served in government, stood for civil rights, started businesses, taught at our Universities, excelled in our sports arenas, won Nobel Prizes, built our tallest building, and lit the Olympic Torch. Denying that fact is baseless, ignorant, and hateful.");

        /* Article with a body only
         */
        $bodyOnlyArticle = (new Model())
            ->setBody("Universities and states, including Illinois, are taking part in a divestment campaign to pressure the Sudanese government to stop the killings. And we will do so in partnership with Muslim communities which are also threatened. This is important because no development strategy can be based only upon what comes out of the ground, nor can it be sustained while young people are out of work. The question is whether we spend that time focused on what pushes us apart, or whether we commit ourselves to an effort - a sustained effort - to find common ground, to focus on the future we seek for our children, and to respect the dignity of all human beings.");

        /* Article with a blurb and a body together
         */
        $bothArticle = (new Model())
            ->setBlurb("Tonight is a particular honor for me because - let's face it - my presence on this stage is pretty unlikely. Like other black churches, Trinity's services are full of raucous laughter and sometimes bawdy humor. He contains within him the contradictions - the good and the bad - of the community that he has served diligently for so many years. But the record's clear: John McCain has voted with George Bush ninety percent of the time. But I am convinced that in order to move forward, we must say openly the things we hold in our hearts, and that too often are said only behind closed doors. In Turkey, Pakistan, Bangladesh and Indonesia, we have seen Muslim-majority countries elect a woman to lead.")
            ->setBody("And I'm glad to see that. Now let there be no doubt. We measure progress by how many people can find a job that pays the mortgage; whether you can put a little extra money away at the end of each month so you can someday watch your child receive her college diploma.");

        /* Article with only files
         */
        $filesOnlyArticle = new Model();
        $filesOnlyArticle->getAttachedFiles()->add(new \Schoolbox\Comms\News\File\Model());

        /* Article with only files, as defined by getAttachedFilesCount rather than the collection
         */
        $fileCountOnlyArticle = (new Model())
            ->setAttachedFilesCount(3);

        /* Article with comment enabled
         */
        $commentOnlyOnArticle = (new Model())
            ->setDiscussionThread((new \Schoolbox\Discussion\Thread\Model())->setOpen(true));

        /* Article with comment enabled, then disabled
         */
        $commentOnlyOffArticle = (new Model())
            ->setDiscussionThread((new \Schoolbox\Discussion\Thread\Model())->setOpen(false));

        /* Article with comments and files
         */
        $filesAndCommentsArticle = (new Model())
            ->setDiscussionThread(new \Schoolbox\Discussion\Thread\Model());
        $filesAndCommentsArticle->getAttachedFiles()->add(new \Schoolbox\Comms\News\File\Model());

        /* Article with everything - the works
         */
        $everythingArticle = (new Model())
            ->setTitle('This is everything')
            ->setBlurb("Tonight is a particular honor for me because - let's face it - my presence on this stage is pretty unlikely. Like other black churches, Trinity's services are full of raucous laughter and sometimes bawdy humor. He contains within him the contradictions - the good and the bad - of the community that he has served diligently for so many years. But the record's clear: John McCain has voted with George Bush ninety percent of the time. But I am convinced that in order to move forward, we must say openly the things we hold in our hearts, and that too often are said only behind closed doors. In Turkey, Pakistan, Bangladesh and Indonesia, we have seen Muslim-majority countries elect a woman to lead.")
            ->setBody("And I'm glad to see that. Now let there be no doubt. We measure progress by how many people can find a job that pays the mortgage; whether you can put a little extra money away at the end of each month so you can someday watch your child receive her college diploma.")
            ->setDiscussionThread(new \Schoolbox\Discussion\Thread\Model());
        ;
        $everythingArticle->getAttachedFiles()->add(new \Schoolbox\Comms\News\File\Model());

        /* More arcane cases
         */

        /* Article with a blurb only, but with comments and files
         */
        $arcane1 = (new Model())
            ->setBlurb("Tonight is a particular honor for me because - let's face it - my presence on this stage is pretty unlikely. Like other black churches, Trinity's services are full of raucous laughter and sometimes bawdy humor. He contains within him the contradictions - the good and the bad - of the community that he has served diligently for so many years. But the record's clear: John McCain has voted with George Bush ninety percent of the time. But I am convinced that in order to move forward, we must say openly the things we hold in our hearts, and that too often are said only behind closed doors. In Turkey, Pakistan, Bangladesh and Indonesia, we have seen Muslim-majority countries elect a woman to lead.")
            ->setDiscussionThread(new \Schoolbox\Discussion\Thread\Model());
        ;
        $arcane1->getAttachedFiles()->add(new \Schoolbox\Comms\News\File\Model());

        return [
            'Empty article, avoid of everything' => [
                $emptyArticle,
                'Read more by clicking here',
                true,
                false
            ],
            'Article with a title only' => [
                $titleOnlyArticle,
                'Read more by clicking here',
                true,
                false
            ],
            'Article with a blurb only' => [
                $blurbOnlyArticle,
                'Read more by clicking here',
                true,
                false
            ],
            'Article with a body only' => [
                $bodyOnlyArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with a blurb and a body together' => [
                $bothArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with only files' => [
                $filesOnlyArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with only files specified by a count' => [
                $fileCountOnlyArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with comment enabled' => [
                $commentOnlyOnArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with comment disabled' => [
                $commentOnlyOffArticle,
                'Read more by clicking here',
                true,
                false
            ],
            'Article with comments and files' => [
                $filesAndCommentsArticle,
                'Read more by clicking here',
                true,
                true
            ],
            'Article with everything - the works' => [
                $everythingArticle,
                'Read more by clicking here',
                true,
                true
            ],

            /* Configuration settings affect the outcome
             */
            'Article with everything, read more string is empty, comments disabled' => [
                $everythingArticle,
                '',
                false,
                false
            ],

            'Article with everything, read more string is empty, comments enabled' => [
                $everythingArticle,
                '',
                true,
                false
            ],

            'Article with everything, read more string exists, comments disabled' => [
                $everythingArticle,
                'Read more by clicking here',
                false,
                true
            ],

            'Article with everything, read more string exists, comments enabled' => [
                $everythingArticle,
                'Read more by clicking here',
                true,
                true
            ],

            /* More arcane cases
             */
            'Article with a blurb only, but with comments and files' => [
                $arcane1,
                'Read more by clicking here',
                true,
                true
            ],
        ];
    }
}
