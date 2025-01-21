<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Metadata\Reader;

class ReaderTest extends TestCase
{
    public function testGetProfileSisPhotoIdWithArray()
    {
        $reader = new Reader();
        $metadata = [
            'profile' => [
                'sisPhotoId' => 12345
            ]
        ];
        $this->assertEquals('12345', $reader->getProfileSisPhotoId($metadata));
    }

    public function testGetProfileSisPhotoIdWithJsonString()
    {
        $reader = new Reader();
        $metadata = '{"profile":{"sisPhotoId":12345}}';
        $this->assertEquals('12345', $reader->getProfileSisPhotoId($metadata));
    }

    public function testGetProfileSisPhotoIdWithNull()
    {
        $reader = new Reader();
        $this->assertNull($reader->getProfileSisPhotoId(null));
    }

    public function testGetProfileSisPhotoIdWithInvalidJson()
    {
        $this->expectException(\JsonException::class);

        $reader = new Reader();
        $metadata = '{"profile":{"sisPhotoId":"12345"';
        $reader->getProfileSisPhotoId($metadata);
    }
}
