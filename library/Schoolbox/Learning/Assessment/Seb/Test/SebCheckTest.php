<?php

/**
 * SebCheckTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Seb\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Seb\ValidSebCheck;
use Schoolbox\Role;
use Schoolbox\User;

class SebCheckTest extends TestCase
{
    /**
     * @dataProvider providerSebCheck
     * @param array  $serverSuperglobal
     * @param string $browserExamKey
     * @param string $configKey
     * @param bool   $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testValidSebCheck(
        array $serverSuperglobal,
        string $browserExamKey,
        string $configKey,
        bool $expected
    ): void {
        $outcome = (new ValidSebCheck())->isSatisfiedBy(
            $serverSuperglobal,
            $browserExamKey,
            $configKey
        );

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidSebCheck
     *
     * @return array
     */
    public function providerSebCheck(): array
    {

        return [

            /* Both keys provided, both match
             */
            'Valid SEB, generic usage' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH' => '041548824a66d177c583ec2f584f2fc9b9a85db8d2b5662988228dfd80d9242a',
                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '0bb0159178965de00b16032d9d3569b10cd386087da40ff2fd082a5db5b7b970',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test'
                ],
                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
                true
            ],

            /* Keys provided don't match what we expected
             */
            'Invalid SEB, keys do not match what we expected' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH' => '041548824a66d177c583ec2f584f2fc9b9a85db8d2b5662988228dfd80d9242a',
                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '0bb0159178965de00b16032d9d3569b10cd386087da40ff2fd082a5db5b7b970',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test'
                ],
                '00258dskjrpwugugnsdy48dfyx6g7usdf5s4s7x9bnksj1iug0234u90g7258999',
                'sogjl258972lkjdljgj289272ld2hfugh27c77b714mazxcr241fa6r949gj48g7',
                false
            ],

            /* Both Keys weren't provided
             */
            'Invalid SEB, bek missing' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '0bb0159178965de00b16032d9d3569b10cd386087da40ff2fd082a5db5b7b970',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test'
                ],
                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
                false
            ],

            'Invalid SEB, config key missing' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH' => '041548824a66d177c583ec2f584f2fc9b9a85db8d2b5662988228dfd80d9242a',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test'
                ],
                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
                false
            ],

            'Invalid SEB, both keys missing' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test'
                ],
                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
                false
            ],

            /* Wrong start url
             */
            'Invalid SEB, start url different' => [
                [
                    'HTTP_SEC_FETCH_USER' => '?1',
                    'HTTP_SEC_FETCH_MODE' => 'navigate',
                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
                    'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH' => '041548824a66d177c583ec2f584f2fc9b9a85db8d2b5662988228dfd80d9242a',
                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
                    'HTTP_CACHE_CONTROL' => 'no-cache',
                    'HTTP_PRAGMA' => 'no-cache',
                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
                    'REDIRECT_STATUS' => '200',
                    'REQUEST_URI' => '/test222'
                ],
                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
                false
            ],
        ];
    }
}
