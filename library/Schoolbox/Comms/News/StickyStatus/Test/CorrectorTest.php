<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\StickyStatus\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\StickyStatus\Corrector;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Comms\News\StickyStatus\Corrector::class)]
class CorrectorTest extends TestCase
{
    /**
     * Test that `\Schoolbox\Comms\News\StickyStatus\Corrector::__invoke`
     * returns an article with the sticky flag set correctly.
     *
     * @param Corrector $corrector
     * @param Model     $article
     * @param bool|null $expectedStickyStatus
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(
        Corrector $corrector,
        Model $article,
        ?bool $expectedStickyStatus,
    ): void {
        $this->assertSame($expectedStickyStatus, $corrector($article)->getSticky());
    }

    /**
     * Test that `\Schoolbox\Comms\News\StickyStatus\Corrector::__invoke`
     * modifies the article passed to it correctly.
     *
     * @param Corrector $corrector
     * @param Model     $article
     * @param bool|null $expectedStickyStatus
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvokeModifiesArticleInPlace(
        Corrector $corrector,
        Model $article,
        ?bool $expectedStickyStatus,
    ): void {
        $corrector($article);
        $this->assertSame($expectedStickyStatus, $article->getSticky());
    }

    /**
     * Data provider for `testInvoke`
     *
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        yield 'config enabled, article enabled => enabled' => [
            new Corrector(true),
            (new Model())->setSticky(true),
            true,
        ];
        yield 'config disabled, article enabled => disabled' => [
            new Corrector(false),
            (new Model())->setSticky(true),
            false,
        ];
        yield 'config enabled, article disabled => disabled' => [
            new Corrector(true),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config disabled, article disabled => disabled' => [
            new Corrector(false),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config enabled, article unset => null' => [
            new Corrector(true),
            new Model(),
            null,
        ];
        yield 'config disabled, article unset => disabled' => [
            new Corrector(false),
            new Model(),
            false,
        ];
    }
}
