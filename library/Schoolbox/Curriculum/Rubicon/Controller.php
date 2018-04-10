<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Rubicon;

use Alaress\Web\Controller\Base;
use GuzzleHttp\Client;

/**
 * Rubicon Atlas controller.
 *
 * So far, contains only a test page.
 */
class Controller extends Base
{
    /**
     * API token for Atlas
     *
     * This is a test token only; it will be replaced with a configurable one.
     *
     * @var string
     */
    private $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6Ijk3NDNkNjVkNzRkN2Q0MTE0MmIxYmNmZjQ0NDg3YTZhNjYyOGFmYjFjNTVjNWEwZjAyMGQ0MzBkNGIwNjRkNDg1MGUzZDliZDgwYmZlNWQyIn0.eyJhdWQiOiIxIiwianRpIjoiOTc0M2Q2NWQ3NGQ3ZDQxMTQyYjFiY2ZmNDQ0ODdhNmE2NjI4YWZiMWM1NWM1YTBmMDIwZDQzMGQ0YjA2NGQ0ODUwZTNkOWJkODBiZmU1ZDIiLCJpYXQiOjE0ODQ4NTg3OTMsIm5iZiI6MTQ4NDg1ODc5MywiZXhwIjoxODAwMzkxNTkzLCJzdWIiOiIyIiwic2NvcGVzIjpbInNjaG9vbGJveCJdfQ.Vv4GBe5NDg9nQd3rZnORMa_GW9RUSi3mXgTG3mJEzxq99GZXgiKtL4CBdeb_m_C69rflV1DBNNGBtmYGgQ1i1neNANExgRqg6hrhk3lNBWv9uJ8HWpvoB8B61eHMJJMwc247t_k9iFLmmMsvr3wuxeOY_7ZYKrjcVuIceAbmT9fBCizIrKmZzAHsgT3NmXnklFz8l2srTaUvbSnvCaOnax4YwPoFUwZdFMQVy4uec7iZyy-kHIjOrlOPtCPL6Q7QxD_0h_-awkSvedhz4XuiHmdtnbUD10TCvsb1VJbm81Cmu_p444PAF3JpadtMcom2h-A74SwjIQxC6aduCZwr8gC-MZott1pBJPzmQ9dN4AlmIrwZttC4gk34faEiWIK8FSqaMeFMK2MLm2ums3Wd_onjq5goYRRXWR9sx4APdPv2DQElMZFXziS8t_IvSi_QnXre7IDpibFGZQ8aUNlkCqyq5ytu21P8NK2mgdxyAYVzmaR9wCmSWn-ogo6SO2x6v633ejIK6OuPRZ5657BygjuS6s3NRomxR5tCw9zlqMyRrXxXIxA5IvvyyUO1x7EhfBatBu3n7ILFyN91D1Eh7_qhMIzShuV0F7X_L0q2CKqgRJKStnjtMraG-oEDfxKL7PowUutT5tCWStKo9YMNiD2Ylk-s_CQvsqqLP2A0ZqE';

    /**
     * Test page for Atlas Rubicon
     */
    public function getTestPage(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@curriculumRubicon/testPage.twig',
                [
                    // Test token
                    'token' => $this->token,
                ]
            )
        );
    }

    /**
     * Proxy for calls to Rubicon's API.
     *
     * @param string $endpoint
     */
    public function getProxy(string $endpoint)
    {
        $client = new Client([
            'base_uri' => 'https://api.rubicon.com/api/',
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
            ],
        ]);

        // pass thru GET params
        $params = [
            'query' => $this->getRequest()->query->getArrayCopy(),
        ];
        unset($params['query']['page']);

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                json_decode($client->get($endpoint, $params)->getBody()->getContents())
            )
        );
    }
}
