<?php

/**
 * StateTransitionTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Moderation\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validation test
 */
class StateTransitionTest extends TestCase
{
    /**
     * @param mixed $moment
     */
    #[DataProvider('providerValidStateTransition')]
    public function testValidStateTransition($moment)
    {
        $jana = (new \Schoolbox\User())->setId(13)->setFirstname('jana')->setLastname('reed');
        $jenni = (new \Schoolbox\User())->setId(64)->setFirstname('jenni')->setLastname('reed');
        $coles = (new \Schoolbox\User())->setId(25)->setFirstname('jack')->setLastname('coles');
        $admin = (new \Schoolbox\User())->setId(1)->setFirstname('alaress')->setLastname('admin');

        /* Submit it
         */
        $moment->submit($jana, new \Alaress_Date_Format('2025-07-20 11:22:33', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertEquals(true, $moment->isSubmitted());
        $this->assertEquals(false, $moment->isPublished());
        $this->assertEquals(false, $moment->isRejected());

        $this->assertEquals('2025-07-20 11:22:33', $moment->getSubmittedAt()?->getDatabase());
        $this->assertEquals($jana->getId(), $moment->getSubmittedBy()?->getId());
        $this->assertEquals(null, $moment->getPublishedAt());
        $this->assertEquals(null, $moment->getPublishedBy());
        $this->assertEquals(null, $moment->getRejectedAt());
        $this->assertEquals(null, $moment->getRejectedBy());

        /* Publish it
         */
        $moment->publish($coles, new \Alaress_Date_Format('2025-07-21 22:33:44', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertEquals(false, $moment->isSubmitted());
        $this->assertEquals(true, $moment->isPublished());
        $this->assertEquals(false, $moment->isRejected());

        $this->assertEquals('2025-07-20 11:22:33', $moment->getSubmittedAt()?->getDatabase());
        $this->assertEquals($jana->getId(), $moment->getSubmittedBy()?->getId());
        $this->assertEquals('2025-07-21 22:33:44', $moment->getPublishedAt()?->getDatabase());
        $this->assertEquals($coles->getId(), $moment->getPublishedBy()?->getId());
        $this->assertEquals(null, $moment->getRejectedAt());
        $this->assertEquals(null, $moment->getRejectedBy());

        /* Reject it
         */
        $moment->reject($admin, new \Alaress_Date_Format('2025-07-23 01:04:27', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertEquals(false, $moment->isSubmitted());
        $this->assertEquals(false, $moment->isPublished());
        $this->assertEquals(true, $moment->isRejected());

        $this->assertEquals(null, $moment->getSubmittedAt()?->getDatabase());
        $this->assertEquals(null, $moment->getSubmittedBy()?->getId());
        $this->assertEquals(null, $moment->getPublishedAt());
        $this->assertEquals(null, $moment->getPublishedBy()?->getId());
        $this->assertEquals('2025-07-23 01:04:27', $moment->getRejectedAt()?->getDatabase());
        $this->assertEquals($admin->getId(), $moment->getRejectedBy()?->getId());

        /* Resubmit it; thanks jana
         */
        $moment->submit($jana, new \Alaress_Date_Format('2025-07-24 01:02:09', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertEquals(true, $moment->isSubmitted());
        $this->assertEquals(false, $moment->isPublished());
        $this->assertEquals(false, $moment->isRejected());

        $this->assertEquals('2025-07-24 01:02:09', $moment->getSubmittedAt()?->getDatabase());
        $this->assertEquals($jana->getId(), $moment->getSubmittedBy()?->getId());
        $this->assertEquals(null, $moment->getPublishedAt());
        $this->assertEquals(null, $moment->getPublishedBy());
        $this->assertEquals(null, $moment->getRejectedAt());
        $this->assertEquals(null, $moment->getRejectedBy());

        /* Publish it again by some other person
         */
        $moment->publish($jenni, new \Alaress_Date_Format('2025-07-24 13:13:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertEquals(false, $moment->isSubmitted());
        $this->assertEquals(true, $moment->isPublished());
        $this->assertEquals(false, $moment->isRejected());

        $this->assertEquals('2025-07-24 01:02:09', $moment->getSubmittedAt()?->getDatabase());
        $this->assertEquals($jana->getId(), $moment->getSubmittedBy()?->getId());
        $this->assertEquals('2025-07-24 13:13:13', $moment->getPublishedAt()?->getDatabase());
        $this->assertEquals($jenni->getId(), $moment->getPublishedBy()?->getId());
        $this->assertEquals(null, $moment->getRejectedAt());
        $this->assertEquals(null, $moment->getRejectedBy());
    }


    /**
     * Data provider for testValidStateTransition
     *
     * @return array
     */
    public static function providerValidStateTransition(): array
    {
        $jana = (new \Schoolbox\User())->setId(13)->setFirstname('jana')->setLastname('reed');

        $moment = (new \Schoolbox\Learning\Moment\Evidence\Model())
            ->setCreator($jana)
            ->setType('text')
            ->setRecipient($jana)
            ->setSubmittedBy($jana);

        return [
            'full process, default datetime' => [
                $moment,
            ],
        ];
    }
}
