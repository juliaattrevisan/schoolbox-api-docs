<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

interface IParser
{
    public function flattenText(string $filePath): string;
}
