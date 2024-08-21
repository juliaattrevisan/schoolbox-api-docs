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
use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\Learning\Assessment\Seb\ValidSebCheck;

class SebCheckTest extends TestCase
{
    /**
     * @param array  $serverSuperglobal
     * @param string $browserExamKey
     * @param string $configKey
     * @param bool   $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSebCheck')]
    public function testValidSebCheck(
        array $serverSuperglobal,
        string $browserExamKey,
        string $configKey,
        bool $expected
    ): void {
        $sebKey = $serverSuperglobal['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] ?? '';
        $outcome = (new ValidSebCheck())->isSatisfiedBy(
            $serverSuperglobal,
            $browserExamKey,
            $configKey,
            $sebKey
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
    public static function providerSebCheck(): array
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
                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '8c75e60739c0c4088552b8022bde7361ee60de68c2e1c2ce7bcfc3d9975e9751',
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
                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '8c75e60739c0c4088552b8022bde7361ee60de68c2e1c2ce7bcfc3d9975e9751',
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
            // TODO: disabling this test as we are not evaluating HTTP_X_SAFEEXAMBROWSER_REQUESTHASH at the moment
//            'Invalid SEB, bek missing' => [
//                [
//                    'HTTP_SEC_FETCH_USER' => '?1',
//                    'HTTP_SEC_FETCH_MODE' => 'navigate',
//                    'HTTP_SEC_FETCH_SITE' => 'same-origin',
//                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
//                    'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH' => '8c75e60739c0c4088552b8022bde7361ee60de68c2e1c2ce7bcfc3d9975e9751',
//                    'HTTP_UPGRADE_INSECURE_REQUESTS' => '1',
//                    'HTTP_SEC_CH_UA_MOBILE' => '?0',
//                    'HTTP_SEC_CH_UA' => '" Not A;Brand";v="99", "Chromium";v="90", "Google Chrome";v="90"',
//                    'HTTP_CACHE_CONTROL' => 'no-cache',
//                    'HTTP_PRAGMA' => 'no-cache',
//                    'HTTP_HOST' => 'vagrant.dev.schoolbox.cloud',
//                    'REDIRECT_STATUS' => '200',
//                    'REQUEST_URI' => '/test'
//                ],
//                'f0d55cfa7ac12bb9bddd3ce12e01dfbff3bcdf8a0d883094b842dca8a050f7e5',
//                'f9d93416762f74970cc7313659e986e9d1518066fb861029d418413661029d2f',
//                false
//            ],

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
