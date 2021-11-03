<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util\Test;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use PHPUnit\Framework\TestCase;
use Schoolbox\LTI\Util\CustomClaimFactory;
use Schoolbox\User;

/**
 * @covers \Schoolbox\LTI\Util\CustomClaimFactory
 */
class CustomClaimFactoryTest extends TestCase
{
    /**
     * @dataProvider getTestBuildData
     * @covers \Schoolbox\LTI\Util\CustomClaimFactory::build
     *
     * @param CustomClaimFactory $factory
     * @param array|null         $claims
     * @param array|null         $expected
     */
    public function testBuild(CustomClaimFactory $factory, ?array $claims, ?array $expected): void
    {
        $actual = $factory->build($claims);
        if (!isset($expected)) {
            $this->assertNull($actual);
        } else {
            $this->assertSame($expected, $actual->normalize());
        }
    }

    public function getTestBuildData(): iterable
    {
        $factory = new CustomClaimFactory();

        // No variables: create no custom claim
        yield 'null' => [$factory, null, null,];

        // If a value is missing but replaceable: do not attempt to replace it
        yield 'replaceable but missing variables' => [
            $factory,
            [
                'id: $User.id',
                'username: $User.username',
                'image: $User.image',
                'org: $User.org',
                'scope mentor: $User.scope.mentor',
            ],
            [
                'id: $User.id',
                'username: $User.username',
                'image: $User.image',
                'org: $User.org',
                'scope mentor: $User.scope.mentor',
            ],
        ];

        // For user and actual user:
        // image, org, scope mentor aren't supported
        $user = (new User())
            ->setId(1)
            ->setExternalId('123')
            ->setUsername('user1');
        yield 'user replacements' => [
            $factory->withUser($user),
            [
                'id: $User.id',
                'username: $User.username',
                'image: $User.image',
                'org: $User.org',
                'scope mentor: $User.scope.mentor',
            ],
            [
                'id: 123',
                'username: user1',
                'image: $User.image',
                'org: $User.org',
                'scope mentor: $User.scope.mentor',
            ],
        ];

        yield 'actual user replacements' => [
            $factory->withActualUser($user),
            [
                'id: $ActualUser.id',
                'username: $ActualUser.username',
                'image: $ActualUser.image',
                'org: $ActualUser.org',
                'scope mentor: $ActualUser.scope.mentor',
            ],
            [
                'id: 123',
                'username: user1',
                'image: $ActualUser.image',
                'org: $ActualUser.org',
                'scope mentor: $ActualUser.scope.mentor',
            ],
        ];

        yield 'resource link replacements: null title+description' => [
            $factory->withResourceLink(
                new ResourceLinkClaim('333', null, null)
            ),
            [
                'id: $ResourceLink.id',
                'title: $ResourceLink.title',
                'description: $ResourceLink.description',
            ],
            [
                'id: 333',
                'title: $ResourceLink.title',
                'description: $ResourceLink.description',
            ],
        ];
        yield 'resource link replacements: non-null title+description' => [
            $factory->withResourceLink(
                new ResourceLinkClaim('333', 'ttt', 'ddd')
            ),
            [
                'id: $ResourceLink.id',
                'title: $ResourceLink.title',
                'description: $ResourceLink.description',
            ],
            [
                'id: 333',
                'title: ttt',
                'description: ddd',
            ],
        ];

        yield 'context link replacements: empty values' => [
            $factory->withContext(new ContextClaim('555', [], null, null)),
            [
                'id: $Context.id',
                'type: $Context.type',
                'label: $Context.label',
                'title: $Context.title',
            ],
            [
                'id: 555',
                'type: ',
                'label: $Context.label',
                'title: $Context.title',
            ],
        ];
        yield 'context link replacements: non-empty values' => [
            $factory->withContext(new ContextClaim('555', ['type1', 'type2'], 'label', 'title')),
            [
                'id: $Context.id',
                'type: $Context.type',
                'label: $Context.label',
                'title: $Context.title',
            ],
            [
                'id: 555',
                'type: type1 type2',
                'label: label',
                'title: title',
            ],
        ];

        yield 'platform instance replacements: empty values' => [
            $factory->withPlatformInstance(
                new PlatformInstanceClaim(
                    '66666',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                )
            ),
            [
                'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version: $ToolPlatformInstance.version',
                'guid: $ToolPlatformInstance.guid',
                'name: $ToolPlatformInstance.name',
                'description: $ToolPlatformInstance.description',
                'url: $ToolPlatformInstance.url',
                'contactEmail: $ToolPlatformInstance.contactEmail',

            ],
            [
                'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version: $ToolPlatformInstance.version',
                'guid: 66666',
                'name: $ToolPlatformInstance.name',
                'description: $ToolPlatformInstance.description',
                'url: $ToolPlatformInstance.url',
                'contactEmail: $ToolPlatformInstance.contactEmail',
            ],
        ];

        yield 'platform instance replacements: non-empty values' => [
            $factory->withPlatformInstance(
                new PlatformInstanceClaim(
                    '66666',
                    'a@b.com',
                    'description',
                    'name',
                    'https://example.com',
                    'example',
                    '1.0.0',
                )
            ),
            [
                'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version: $ToolPlatformInstance.version',
                'guid: $ToolPlatformInstance.guid',
                'name: $ToolPlatformInstance.name',
                'description: $ToolPlatformInstance.description',
                'url: $ToolPlatformInstance.url',
                'contactEmail: $ToolPlatformInstance.contactEmail',

            ],
            [
                'productFamilyCode: example',
                'version: 1.0.0',
                'guid: 66666',
                'name: name',
                'description: description',
                'url: https://example.com',
                'contactEmail: a@b.com',
            ],
        ];

        yield 'multiple replacements in one custom value' => [
            $factory
                ->withUser($user)
                ->withPlatformInstance(new PlatformInstanceClaim('66666')),
            [
                'userId: $User.id, platformGuid: $ToolPlatformInstance.guid',
            ],
            [
                'userId: 123, platformGuid: 66666',
            ],
        ];
    }
}
