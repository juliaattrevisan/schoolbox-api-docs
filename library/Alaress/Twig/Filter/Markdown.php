<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Twig\Filter;

use League\CommonMark\MarkdownConverterInterface;

/**
 * Twig filter for converting Markdown formatted text to HTML.
 */
class Markdown
{
    /**
     * @var MarkdownConverterInterface
     */
    private $converter;


    /**
     * Constructor
     *
     * @param MarkdownConverterInterface $converter
     */
    public function __construct(MarkdownConverterInterface $converter)
    {
        $this->converter = $converter;
    }


    /**
     * Converts text using the Markdown converter in this object.
     *
     * @param mixed $text
     * @return string
     */
    public function __invoke($text)
    {
        return $this->converter->convertToHtml($text);
    }
}
