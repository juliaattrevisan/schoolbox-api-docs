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
    private $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImFhYTQ3MDAyNjY2YWJlOWI0MDAyYzk5MTQ3ODIzNWNmZDZhYmM1YTllNDRlZDQ5MDFhZWJjZjg0ZTA0MjE3NTlmZTI1M2UwNGMwYWM3MDQ3In0.eyJhdWQiOiIxIiwianRpIjoiYWFhNDcwMDI2NjZhYmU5YjQwMDJjOTkxNDc4MjM1Y2ZkNmFiYzVhOWU0NGVkNDkwMWFlYmNmODRlMDQyMTc1OWZlMjUzZTA0YzBhYzcwNDciLCJpYXQiOjE1MjA5NTc0ODUsIm5iZiI6MTUyMDk1NzQ4NSwiZXhwIjoxNTUyNDkzNDg1LCJzdWIiOiIyIiwic2NvcGVzIjpbInN3YWdnZXIiXX0.s7iDr0PyNMPOLpTvnhEH-Z5a860VxCaj364BKx862LoEh-uF4AzecEYnEyRjbK6uvmacK24GtXAukh6aawTRy9LyGva5Q0IyfqP3oDiAk3oiYUH69hokv6_g8kWpc1wjwmI1GTmYR4Uq0bLL8r-aUBft5ZXKOvIlOTmtKs58ZWsixTaGpcWrIiXoBC7tlVC3MdDX0mhG5aP645Ca8dhQnkT_AkI9BaPbJt61MzJQarTYU4KE25asspSn2TvkLj-Ipw2ASn5E8v3I-VM5QNxh2Ppimjpiw4bwFi1Q_Sio-W4F8bzdGiJ0PANXYL5w87P3m1FuJe2x5L1TbrVpGItAFIY_3kdxFd1rdhVQLseEN0GuptHaIwNBqBTRsJYYVH-p-nzg-BITSHlnC2ulL8gWwGLc9N0vyV0JWTwqC_gR2VILVyiG40S07qW49hC4scyiis021lBvKPGMt9w_GgKiylGMuZ3aIOztEL4wairxZwXtFNlspcHJ9JC_aDWZzBywNVu3zEE-11PPCeXR739NOzBLYH8CJxQ0YICHXpkTMjn0XHogWuLg0REFsIyXsT3YPH_qUlB4ez3tBUQUXSi2TQy11QUvUAy0dJAEjOlERYV14X01BhXx34juEiczXK4ESU62c2GzaYJaHqBLu9WWZumawchyxO8owW0dac47vds';

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
