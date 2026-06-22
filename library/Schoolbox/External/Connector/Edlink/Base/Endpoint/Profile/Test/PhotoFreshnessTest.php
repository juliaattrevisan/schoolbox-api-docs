<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile\PhotoFreshness;

class PhotoFreshnessTest extends TestCase
{
    public function testDownloadsWhenNoPhotoStoredYet(): void
    {
        $sourceUpdatedAt = Alaress_Date_Format::newFromTimestamp(1_000);

        $this->assertTrue(PhotoFreshness::needsDownload(null, $sourceUpdatedAt));
    }

    public function testDownloadsWhenSourceHasNoUpdatedDate(): void
    {
        $storedAt = Alaress_Date_Format::newFromTimestamp(1_000);

        $this->assertTrue(PhotoFreshness::needsDownload($storedAt, null));
    }

    public function testDownloadsWhenSourceChangedAfterPhotoStored(): void
    {
        $storedAt        = Alaress_Date_Format::newFromTimestamp(1_000);
        $sourceUpdatedAt = Alaress_Date_Format::newFromTimestamp(2_000);

        $this->assertTrue(PhotoFreshness::needsDownload($storedAt, $sourceUpdatedAt));
    }

    public function testSkipsWhenSourceUnchangedSincePhotoStored(): void
    {
        $storedAt        = Alaress_Date_Format::newFromTimestamp(2_000);
        $sourceUpdatedAt = Alaress_Date_Format::newFromTimestamp(1_000);

        $this->assertFalse(PhotoFreshness::needsDownload($storedAt, $sourceUpdatedAt));
    }

    public function testSkipsWhenSourceTimestampEqualsStored(): void
    {
        $storedAt        = Alaress_Date_Format::newFromTimestamp(1_000);
        $sourceUpdatedAt = Alaress_Date_Format::newFromTimestamp(1_000);

        $this->assertFalse(PhotoFreshness::needsDownload($storedAt, $sourceUpdatedAt));
    }
}
