<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\User;
use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Comms\News\Exceptions\InvalidTransitionException;
use PHPUnit\Framework;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Comms\News\Model::class)]
class StateTransitionTest extends TestCase
{

    /**
     * Test we can transition an article through valid transitions
     *
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testTransitionsNormal(Model $article, User $moderator): void
    {
        // New models are drafts
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Pretend that we saved it
        $article
            ->setId(1)
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setCreatedAt(Alaress_Date_Format::now());

        // Still a draft
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Submit for moderation
        $article->submit();
        $this->assertTrue($article->isAwaitingModeration());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Reject it
        $rejectionReason = 'cause i can';
        $article->reject($moderator, $rejectionReason);
        $this->assertTrue($article->isRejected());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Resubmit it
        $article->submit();
        $this->assertTrue($article->isAwaitingModeration());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // The reason is still there so the reviewer can see why it was rejected
        $this->assertEquals($rejectionReason, $article->getRejectedReason());

        // Approve it, rejection reasons are cleared
        $article->approve($moderator);
        $this->assertTrue($article->isApproved());
        $this->assertNull($article->getRejectedReason());
        $this->assertFalse($article->isPublic());

        // Send it back to draft
        $article->unSubmit();
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Approve it
        $article->approve($moderator);
        $this->assertTrue($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Reject it
        $article->reject($moderator);
        $this->assertTrue($article->isRejected());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());

        // Send it back to draft
        $article->unSubmit();
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());
        $this->assertFalse($article->isPublic());
    }


    /**
     * Tests the approved composite state
     *
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidArgumentException
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testApprovedStates(Model $article, User $moderator): void
    {
        $article->approve($moderator);

        // Currently awaiting publication
        $this->assertTrue($article->isAwaitingPublication());
        $this->assertFalse($article->isPublic());

        // Still awaiting in 5 days
        $this->assertTrue($article->isAwaitingPublication(Alaress_Date_Format::newNaturalDate('5 days')));
        $this->assertFalse($article->isPublic());

        // published in 10
        $this->assertTrue($article->isPublished(Alaress_Date_Format::newNaturalDate('10 days')));
        $this->assertTrue($article->isPublic(Alaress_Date_Format::newNaturalDate('10 days')));

        // expired in 20
        $this->assertTrue($article->isExpired(Alaress_Date_Format::newNaturalDate('20 days')));
        $this->assertTrue($article->isPublic(Alaress_Date_Format::newNaturalDate('20 days')));

        // But these are all sub-states of the approved state
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('5 days')));
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('10 days')));
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('20 days')));
    }


    /**
     * Tests approval transitions
     *
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testApprovedTransitions(Model $article, User $moderator): void
    {
        $article->approve($moderator);

        // Currently awaiting publication
        $this->assertTrue($article->isAwaitingPublication());

        $article->publish();
        $this->assertTrue($article->isPublished());

        $article->archive();
        $this->assertTrue($article->isExpired());

        $article->unSubmit();
        $this->assertTrue($article->isDraft());

        $article->submit();
        $this->assertTrue($article->isAwaitingModeration());

        $article->approve($moderator);
        $this->assertTrue($article->isExpired());
    }


    /**
     * Tests we cannot reject a draft article
     *
     * @param Model $article
     * @param User  $moderator
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionRejectDraft(Model $article, User $moderator): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->reject($moderator);
    }


    /**
     * Tests we cannot reject an already-rejected article
     *
     * @param Model $article
     * @param User  $moderator
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionRejectReject(Model $article, User $moderator): void
    {
        $article->submit();
        $article->reject($moderator);

        $this->expectException(InvalidTransitionException::class);
        $article->reject($moderator);
    }


    /**
     * Tests we cannot submit an article already awaiting moderation
     *
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionSubmitAwaitingModeration(Model $article): void
    {
        $article->submit();
        $this->expectException(InvalidTransitionException::class);
        $article->submit();
    }


    /**
     * Tests we cannot submit an article already awaiting moderation
     *
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionSubmitExpired(Model $article): void
    {
        $article->submit();
        $article->approve(self::getModerator());
        $article->archive();
        $this->expectException(InvalidTransitionException::class);
        $article->submit();
    }


    /**
     * Tests we cannot publish a draft
     *
     * @throws InvalidTransitionException
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     */
    public function testInvalidTransitionPublishDraft(): void
    {
        $article = (new Model())
            ->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'))
            ->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));

        $this->expectException(InvalidTransitionException::class);
        $article->publish();
    }


    /**
     * Tests we cannot un-submit a draft
     *
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionUnSubmitDraft(Model $article): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->unSubmit();
    }


    /**
     * Tests we cannot archive a draft
     *
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionUnArchiveDraft(Model $article): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests we cannot archive an article awaiting moderation
     *
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionUnArchiveAwaitingMod(Model $article): void
    {
        $article->submit();
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests we cannot archive a rejected article
     *
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionUnArchiveRejected(Model $article, User $moderator): void
    {
        $article->submit();
        $article->reject($moderator);
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests we cannot archive an already-archived article
     *
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws Framework\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testInvalidTransitionUnArchiveArchived(Model $article, User $moderator): void
    {
        $article->submit();
        $article->approve($moderator);
        $article->archive();
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests that if we extend a publish date into the future, it automatically pushes out the expiry date
     * so that no invalid data occurs (cannot have expiry < publish)
     *
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws InvalidArgumentException
     * @throws AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStubArticleAndModerator')]
    public function testPublishAfterExpiry(Model $article, User $moderator): void
    {
        $article->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'));
        $article->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));
        $article->submit();
        $article->approve($moderator);
        $article->publish(Alaress_Date_Format::newNaturalDate('20 days'));

        $this->assertFalse($article->getExpiresAt()->lt($article->getPublishAt()));
    }

    /**
     * Tests whether the available transitions per atomic state
     *
     * @throws AssertionFailedError
     * @throws InvalidArgumentException
     * @throws InvalidTransitionException
     */
    public function testAvailableTransitions(): void
    {
        // Created now, publish in 7 days, expire in 14 days
        $article = (new Model())
            ->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'))
            ->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));

        // Draft
        $this->assertTrue($article->canBeSubmitted());
        $this->assertFalse($article->canBeUnSubmitted());
        $this->assertTrue($article->canBeApproved());
        $this->assertFalse($article->canBeRejected());
        $this->assertFalse($article->canBeArchived());

        // Awaiting moderation
        $article->submit();
        $this->assertFalse($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertTrue($article->canBeApproved());
        $this->assertTrue($article->canBeRejected());
        $this->assertFalse($article->canBeArchived());

        // Rejected
        $article->reject(self::getModerator());
        $this->assertTrue($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertTrue($article->canBeApproved());
        $this->assertFalse($article->canBeRejected());
        $this->assertFalse($article->canBeArchived());

        // Awaiting moderation
        $article->submit();
        $this->assertFalse($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertTrue($article->canBeApproved());
        $this->assertTrue($article->canBeRejected());
        $this->assertFalse($article->canBeArchived());

        // Awaiting publication
        $article->approve(self::getModerator());
        $this->assertFalse($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertFalse($article->canBeApproved());
        $this->assertTrue($article->canBeRejected());
        $this->assertTrue($article->canBeArchived());

        // Published
        $article->publish();
        $this->assertFalse($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertFalse($article->canBeApproved());
        $this->assertTrue($article->canBeRejected());
        $this->assertTrue($article->canBeArchived());

        // Expired
        $article->archive();
        $this->assertFalse($article->canBeSubmitted());
        $this->assertTrue($article->canBeUnSubmitted());
        $this->assertFalse($article->canBeApproved());
        $this->assertTrue($article->canBeRejected());
        $this->assertFalse($article->canBeArchived());
    }

    // Data providers


    /**
     * Returns a stub article and moderator. Article due to be published in 7 days, and expire in 14 days
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public static function providerStubArticleAndModerator(): array
    {
        // Created now, publish in 7 days, expire in 14 days
        $article = (new Model())
            ->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'))
            ->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));

        $moderator = self::getModerator();

        return [
            [$article, $moderator],
        ];
    }

    // Helpers

    /**
     * Returns a user we can set as moderator
     *
     * @return User
     */
    private static function getModerator(): User
    {
        return (new User())->setId(151);
    }
}
