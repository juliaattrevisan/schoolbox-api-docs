<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Custom\Test;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use PHPUnit\Framework\TestCase;
use Schoolbox\LTI\Custom\ClaimFactory;
use Schoolbox\User;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\LTI\Custom\ClaimFactory::class)]
class ClaimFactoryTest extends TestCase
{
    /**
     *
     * @param ClaimFactory $factory
     * @param array|null   $claims
     * @param array|null   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestBuildData')]
    public function testBuild(ClaimFactory $factory, ?array $claims, ?array $expected): void
    {
        $actual = $factory->build($claims);
        if (!isset($expected)) {
            $this->assertNull($actual);
        } else {
            $this->assertSame($expected, $actual->normalize());
        }
    }

    public static function getTestBuildData(): iterable
    {
        $factory = new ClaimFactory();

        // No variables: create no custom claim
        yield 'null' => [$factory, null, null,];
        yield 'empty' => [$factory, [], [],];

        // If a value is missing but replaceable: do not attempt to replace it
        yield 'replaceable but missing variables' => [
            $factory,
            [
                'id'       => 'id: $User.id',
                'username' => 'username: $User.username',
                'image'    => 'image: $User.image',
                'org'      => 'org: $User.org',
                'scope'    => 'scope mentor: $User.scope.mentor',
            ],
            [
                'id'       => 'id: $User.id',
                'username' => 'username: $User.username',
                'image'    => 'image: $User.image',
                'org'      => 'org: $User.org',
                'scope'    => 'scope mentor: $User.scope.mentor',
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
                'id'       => 'id: $User.id',
                'username' => 'username: $User.username',
                'image'    => 'image: $User.image',
                'org'      => 'org: $User.org',
                'scope'    => 'scope mentor: $User.scope.mentor',
            ],
            [
                'id'       => 'id: 123',
                'username' => 'username: user1',
                'image'    => 'image: $User.image',
                'org'      => 'org: $User.org',
                'scope'    => 'scope mentor: $User.scope.mentor',
            ],
        ];

        yield 'actual user replacements' => [
            $factory->withActualUser($user),
            [
                'id'       => 'id: $ActualUser.id',
                'username' => 'username: $ActualUser.username',
                'image'    => 'image: $ActualUser.image',
                'org'      => 'org: $ActualUser.org',
                'scope'    => 'scope mentor: $ActualUser.scope.mentor',
            ],
            [
                'id'       => 'id: 123',
                'username' => 'username: user1',
                'image'    => 'image: $ActualUser.image',
                'org'      => 'org: $ActualUser.org',
                'scope'    => 'scope mentor: $ActualUser.scope.mentor',
            ],
        ];

        yield 'resource link replacements: null title+description' => [
            $factory->withResourceLink(
                new ResourceLinkClaim('333', null, null)
            ),
            [
                'id'          => 'id: $ResourceLink.id',
                'title'       => 'title: $ResourceLink.title',
                'description' => 'description: $ResourceLink.description',
            ],
            [
                'id'          => 'id: 333',
                'title'       => 'title: $ResourceLink.title',
                'description' => 'description: $ResourceLink.description',
            ],
        ];
        yield 'resource link replacements: non-null title+description' => [
            $factory->withResourceLink(
                new ResourceLinkClaim('333', 'ttt', 'ddd')
            ),
            [
                'id'          => 'id: $ResourceLink.id',
                'title'       => 'title: $ResourceLink.title',
                'description' => 'description: $ResourceLink.description',
            ],
            [
                'id'          => 'id: 333',
                'title'       => 'title: ttt',
                'description' => 'description: ddd',
            ],
        ];

        yield 'context link replacements: empty values' => [
            $factory->withContext(new ContextClaim('555', [], null, null)),
            [
                'id'    => 'id: $Context.id',
                'type'  => 'type: $Context.type',
                'label' => 'label: $Context.label',
                'title' => 'title: $Context.title',
            ],
            [
                'id'    => 'id: 555',
                'type'  => 'type: ',
                'label' => 'label: $Context.label',
                'title' => 'title: $Context.title',
            ],
        ];
        yield 'context link replacements: non-empty values' => [
            $factory->withContext(new ContextClaim('555', ['type1', 'type2'], 'label', 'title')),
            [
                'id'    => 'id: $Context.id',
                'type'  => 'type: $Context.type',
                'label' => 'label: $Context.label',
                'title' => 'title: $Context.title',
            ],
            [
                'id'    => 'id: 555',
                'type'  => 'type: type1 type2',
                'label' => 'label: label',
                'title' => 'title: title',
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
                'productFamilyCode' => 'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version'           => 'version: $ToolPlatformInstance.version',
                'guid'              => 'guid: $ToolPlatformInstance.guid',
                'name'              => 'name: $ToolPlatformInstance.name',
                'description'       => 'description: $ToolPlatformInstance.description',
                'url'               => 'url: $ToolPlatformInstance.url',
                'contactEmail'      => 'contactEmail: $ToolPlatformInstance.contactEmail',

            ],
            [
                'productFamilyCode' => 'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version'           => 'version: $ToolPlatformInstance.version',
                'guid'              => 'guid: 66666',
                'name'              => 'name: $ToolPlatformInstance.name',
                'description'       => 'description: $ToolPlatformInstance.description',
                'url'               => 'url: $ToolPlatformInstance.url',
                'contactEmail'      => 'contactEmail: $ToolPlatformInstance.contactEmail',
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
                'productFamilyCode' => 'productFamilyCode: $ToolPlatformInstance.productFamilyCode',
                'version'           => 'version: $ToolPlatformInstance.version',
                'guid'              => 'guid: $ToolPlatformInstance.guid',
                'name'              => 'name: $ToolPlatformInstance.name',
                'description'       => 'description: $ToolPlatformInstance.description',
                'url'               => 'url: $ToolPlatformInstance.url',
                'contactEmail'      => 'contactEmail: $ToolPlatformInstance.contactEmail',

            ],
            [
                'productFamilyCode' => 'productFamilyCode: example',
                'version'           => 'version: 1.0.0',
                'guid'              => 'guid: 66666',
                'name'              => 'name: name',
                'description'       => 'description: description',
                'url'               => 'url: https://example.com',
                'contactEmail'      => 'contactEmail: a@b.com',
            ],
        ];

        yield 'multiple replacements in one custom value' => [
            $factory
                ->withUser($user)
                ->withPlatformInstance(new PlatformInstanceClaim('66666')),
            [
                'test' => 'userId: $User.id, platformGuid: $ToolPlatformInstance.guid',
            ],
            [
                'test' => 'userId: 123, platformGuid: 66666',
            ],
        ];
    }
}
