<?php

class SmokeInitialCest {

    public function CreateNews(IntegrationTester $i){
        $data = array(
            'page' => '/login/',
            'username' => 'alaress',
            'password' => 'alaress',
            'Submit' => 'Login'
        );

        $page='/login/';
        $url = 'https://vagrant.dev.schoolbox.cloud'.$page;
        $ch = curl_init($url);
        $postString = http_build_query($data, '', '&');

        $User_Agent = 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31';

        $request_headers = array();
        $request_headers[] = 'Host: vagrant.dev.schoolbox.cloud';
        $request_headers[] = 'User-Agent: '.$User_Agent;
        $request_headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $request_headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $request_headers[] = 'Content-Length: 63';
        $request_headers[] = 'Referer: https://vagrant.dev.schoolbox.cloud/login/';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $response = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);

        $header_temp=explode(PHP_EOL, $header);

        curl_close($ch);

        print_r($header_temp);



    }
}

//PHPSESSID=dua5o1pn0jmmhp4g9iq9l09mnk; expires=Tue, 25-Jun-2019 00:14:41 GMT; Max-Age=3600; path=/; HttpOnly