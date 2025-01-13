<?php

/**
  * @copyright   Copyright 2025, Schoolbox Pty Ltd
  * @link        https://schoolbox.education/
  */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Metadata\Builder;

class BuilderTest extends TestCase
{
    public function testBuildWithValidProfileInstance()
    {
        $builder = new Builder();
        $profileInstance = [
            'profile' => [
                'sisPhotoId' => '12345'
            ]
        ];
        $expected = [
            'profile' => [
                'sisPhotoId' => '12345'
            ]
        ];
        $this->assertEquals($expected, $builder->build($profileInstance));
    }

    public function testBuildWithMissingRollMarkingUrl()
    {
        $builder = new Builder();
        $profileInstance = [
            'attributes' => []
        ];
        $expected = [
            'profile' => [
                'sisPhotoId' => null
            ]
        ];
        $this->assertEquals($expected, $builder->build($profileInstance));
    }

    public function testBuildWithEmptyProfileInstance()
    {
        $builder = new Builder();
        $profileInstance = [];
        $expected = [
            'profile' => [
                'sisPhotoId' => null
            ]
        ];
        $this->assertEquals($expected, $builder->build($profileInstance));
    }
}
