<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Json\Test\Decorator;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Api\Link;
use Alaress\Json\Linkable;
use Aura\Router\RouterContainer;
use Schoolbox\Json\Api\AuraLink;
use Schoolbox\Json\Api\SitePageLink;
use Schoolbox\Json\Decorator\LinkDecorator;
use PHPUnit\Framework\TestCase;

class LinkDecoratorTest extends TestCase
{
    /** @var RouterContainer */
    private RouterContainer $routerContainer;

    /** @var Authorizer */
    private $auth;

    /** @var Linkable */
    private $linkable;

    /**
     * This method is called before the first test of this test class is run.
     */
    public function setUp(): void
    {
        $this->routerContainer = new RouterContainer();
        $this->routerContainer->getMap()->route('test.link', '/test/link');

        $this->auth = $this->getMockBuilder(Authorizer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([ 'isPathAuthorizedForUser' ])
            ->getMock();

        $this->auth
            ->method('isPathAuthorizedForUser')
            ->willReturn(null);


        $this->linkable = new class implements Linkable {
            /**
             * @return Link[]
             */
            public function getJsonApiLinks(): array
            {
                return [
                    'aura' => new AuraLink('test.link'),
                    'site' => new SitePageLink('/test/link')
                ];
            }
        };
    }


    /**
     */
    public function testDecorate()
    {
        $link = [
            'href' => '/test/link',
            'auth' => null
        ];

        $expected = [
            '_links' => [
                'aura' => $link,
                'site' => $link
            ]
        ];

        $dec = new LinkDecorator($this->routerContainer->getGenerator(), $this->auth, null);

        $actual = $dec->decorate([], $this->linkable);

        $this->assertEquals($expected, $actual);
    }
}
