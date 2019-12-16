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
 * @covers \Schoolbox\Comms\News\Model
 * @codeCoverageIgnore
 */
class StateTransitionTest extends TestCase
{

    /**
     * Test we can transition an article through valid transitions
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidTransitionException
     */
    public function testTransitionsNormal(Model $article, User $moderator): void
    {
        // New models are drafts
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());

        // Pretend that we saved it
        $article
            ->setId(1)
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setCreatedAt(Alaress_Date_Format::now());

        // Still a draft
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());

        // Submit for moderation
        $article->submit();
        $this->assertTrue($article->isAwaitingModeration());
        $this->assertFalse($article->isApproved());

        // Reject it
        $rejectionReason = 'cause i can';
        $article->reject($moderator, $rejectionReason);
        $this->assertTrue($article->isRejected());
        $this->assertFalse($article->isApproved());

        // Resubmit it
        $article->submit();
        $this->assertTrue($article->isAwaitingModeration());
        $this->assertFalse($article->isApproved());

        // The reason is still there so the reviewer can see why it was rejected
        $this->assertEquals($rejectionReason, $article->getRejectedReason());

        // Approve it, rejection reasons are cleared
        $article->approve($moderator);
        $this->assertTrue($article->isApproved());
        $this->assertNull($article->getRejectedReason());

        // Send it back to draft
        $article->unSubmit();
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());

        // Approve it
        $article->approve($moderator);
        $this->assertTrue($article->isApproved());

        // Reject it
        $article->reject($moderator);
        $this->assertTrue($article->isRejected());
        $this->assertFalse($article->isApproved());

        // Send it back to draft
        $article->unSubmit();
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isApproved());
    }


    /**
     * Tests the approved composite state
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidArgumentException
     * @throws InvalidTransitionException
     */
    public function testApprovedStates(Model $article, User $moderator): void
    {
        $article->approve($moderator);

        // Currently awaiting publication
        $this->assertTrue($article->isAwaitingPublication());

        // Still awaiting in 5 days
        $this->assertTrue($article->isAwaitingPublication(Alaress_Date_Format::newNaturalDate('5 days')));

        // published in 10
        $this->assertTrue($article->isPublished(Alaress_Date_Format::newNaturalDate('10 days')));

        // expired in 20
        $this->assertTrue($article->isExpired(Alaress_Date_Format::newNaturalDate('20 days')));

        // But these are all sub-states of the approved state
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('5 days')));
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('10 days')));
        $this->assertTrue($article->isApproved(Alaress_Date_Format::newNaturalDate('20 days')));
    }


    /**
     * Tests approval transitions
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws AssertionFailedError
     * @throws InvalidTransitionException
     */
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
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    public function testInvalidTransitionRejectDraft(Model $article, User $moderator): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->reject($moderator);
    }


    /**
     * Tests we cannot reject an already-rejected article
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    public function testInvalidTransitionRejectReject(Model $article, User $moderator): void
    {
        $article->submit();
        $article->reject($moderator);

        $this->expectException(InvalidTransitionException::class);
        $article->reject($moderator);
    }


    /**
     * Tests we cannot un-submit a draft
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    public function testInvalidTransitionUnSubmitDraft(Model $article): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->unSubmit();
    }


    /**
     * Tests we cannot archive a draft
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    public function testInvalidTransitionUnArchiveDraft(Model $article): void
    {
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests we cannot archive an article awaiting moderation
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @throws Framework\Exception
     * @throws InvalidTransitionException
     */
    public function testInvalidTransitionUnArchiveAwaitingMod(Model $article): void
    {
        $article->submit();
        $this->expectException(InvalidTransitionException::class);
        $article->archive();
    }


    /**
     * Tests we cannot archive a rejected article
     *
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws Framework\Exception
     */
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
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws Framework\Exception
     */
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
     * @dataProvider providerStubArticleAndModerator
     * @param Model $article
     * @param User  $moderator
     * @throws InvalidTransitionException
     * @throws InvalidArgumentException
     * @throws AssertionFailedError
     */
    public function testPublishAfterExpiry(Model $article, User $moderator): void
    {
        $article->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'));
        $article->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));
        $article->submit();
        $article->approve($moderator);
        $article->publish(Alaress_Date_Format::newNaturalDate('20 days'));

        $this->assertFalse($article->getExpiresAt()->lt($article->getPublishAt()));
    }

    // Data providers


    /**
     * Returns a stub article and moderator. Article due to be published in 7 days, and expire in 14 days
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function providerStubArticleAndModerator(): array
    {
        // Created now, publish in 7 days, expire in 14 days
        $article = (new Model())
            ->setPublishAt(Alaress_Date_Format::newNaturalDate('7 days'))
            ->setExpiresAt(Alaress_Date_Format::newNaturalDate('14 days'));

        $moderator = (new User())->setId(151);

        return [
            [$article, $moderator],
        ];
    }
}
