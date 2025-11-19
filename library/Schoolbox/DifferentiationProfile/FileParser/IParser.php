<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

interface IParser
{
    /**
     * Extract and flatten text from a file at the given path
     *
     * @param string $filePath The path to the file to extract text from
     * @return string The extracted and flattened text
     */
    public function flattenText(string $filePath): string;
}
