<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Proxy\Server;

/**
 * Class Alaress_Curl_Url
 */
class Alaress_Curl_Url
{

    /**
     * Errors returned by curl are collated into this stack
     *
     * @var array
     */
    private $errors = [];


    /**
     * curl response string
     *
     * @var string
     */
    private $result = '';


    /**
     * curl response code
     *
     * @var int
     */
    private $responseCode = 0;


    /**
     * Default options
     *
     * @var array
     */
    private $options = [
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => 1,
    ];


    /**
     * curl source url
     *
     * @var string
     */
    private $url;


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }


    /**
     * Add implementation-specific options
     *
     * @param array $newOptions
     *
     * @return $this
     */
    public function addOptions(array $newOptions): self
    {

        /* Use array union operator to glue these two arrays together
         * and preserve indexes. array_merge() loses numerical indexes
         *
         * http://php.net/manual/en/language.operators.array.php
         */
        $this->options += $newOptions;

        return $this;
    }


    /**
     * Execute curl request and store result
     *
     * @return $this
     */
    public function exec(): self
    {
        $ch = Server::getInstance()->curlInit($this->url);

        curl_setopt_array($ch, $this->options);

        $this->result = curl_exec($ch);
        $this->addErrors(curl_error($ch));
        $this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $this;
    }


    /**
     * Add errors
     *
     * @param string $error
     */
    private function addErrors(string $error): void
    {
        if ($error !== '') {
            $this->errors[] = $error;
        }
    }


    /* Getters/Setters
     */

    /**
     * Get errors array
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }


    /**
     * Get curl result
     *
     * @return string
     */
    public function getResult(): string
    {
        return $this->result;
    }


    /**
     * @return int
     */
    public function getResponseCode(): int
    {
        return $this->responseCode;
    }
}
