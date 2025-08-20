<?php

/**
 * NormaliseContentTest
 *
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

class NormaliseContentTest extends TestCase
{
    /**
     * Test that running BaseBuilder->normaliseContent() returns
     * the expected output for each case in the test data set.
     */
    #[DataProvider('providerTestNormaliseContent')]
    public function testNormaliseContent($content, $strategy, $expected): void
    {
        $this->assertSame(
            BaseBuilder::normaliseContent($content, $strategy),
            $expected,
            "Testing content '$content' with strategy '$strategy'."
        );
    }

    /**
     * @return array[]
     */
    public static function providerTestNormaliseContent(): array
    {
        return [
            // Each test data array below has: [content, strategy, expectedOutput]
            [
                '<p>ddg test for 9enga</p><p>arch also</p>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'ddg test for 9enga arch also',
            ],
            [
                '<div><p>Stack</p><div><p>of</p><div><p>Nested</p><div>Divs</div><p>one</p></div><p>two</p></div><p>three</p></div>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Stack of Nested Divs one two three',
            ],

            // AI-generated test cases follow.

            // HTML Entities decoded by htmlspecialchars_decode()
            [
                'Testing &amp; encoding &lt;script&gt; and &quot;quotes&quot; &#039;here&#039;',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Testing & encoding <script> and "quotes" \'here\'',
            ],

            // HTML Entities decoded by html_entity_decode() - broader coverage
            [
                'Testing &amp; &lt;script&gt; &quot;quotes&quot; &#039;apostrophe&#039; &copy; &reg; &trade; &euro; &pound; &yen; &sect; &para; &middot; &raquo; &laquo; &hellip;',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Testing & <script> "quotes" \'apostrophe\' © ® ™ € £ ¥ § ¶ · » « …',
            ],

            // Multiple Spaces and Whitespace
            [
                'Multiple    spaces     should    be    normalized',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Multiple spaces should be normalized',
            ],
            [
                "Tabs\t\tand\tnewlines\n\nshould\r\nbe\r\n\rnormalized",
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Tabs and newlines should be normalized',
            ],
            [
                '   Leading and trailing spaces   ',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Leading and trailing spaces',
            ],
            [
                '<p>HTML   with    multiple</p><div>    spaces   </div>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'HTML with multiple spaces',
            ],

            // Complex HTML Scenarios
            [
                '<p>Text with <strong>bold</strong> and <em>italic</em> formatting</p>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Text with bold and italic formatting',
            ],
            [
                '<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Item 1 Item 2 Item 3',
            ],
            [
                '<a href="https://example.com">Link text</a> and <img src="image.jpg" alt="Alt text">',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Link text and',
            ],
            [
                // The intention here is just to strip the tags, not to remove the content.
                '<script>alert("malicious");</script>Content<style>.class{color:red;}</style>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'alert("malicious"); Content .class{color:red;}',
            ],
            [
                '<div class="wrapper" id="main" data-attr="value">Content here</div>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Content here',
            ],

            // Self-closing and Void Elements
            [
                'Text<br/>with<hr/>breaks<img src="test.jpg"/>here',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Text with breaks here',
            ],
            [
                '<input type="text" value="input value"><meta name="test">Content',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Content',
            ],

            // Malformed HTML
            [
                '<p>Unclosed paragraph<div>Mixed nesting</p></div>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Unclosed paragraph Mixed nesting',
            ],
            [
                '<>Empty tags</> and <invalid-tag>content</invalid-tag>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Empty tags and content',
            ],
            [
                'Text with < unescaped > brackets',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Text with < unescaped > brackets',
            ],

            // Edge Cases
            [
                '',
                BaseBuilder::INDEX_STRATEGY_HTML,
                '',
            ],
            [
                '   ',
                BaseBuilder::INDEX_STRATEGY_HTML,
                '',
            ],
            [
                '<p></p><div></div><span></span>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                '',
            ],
            [
                'Plain text with no HTML',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Plain text with no HTML',
            ],

            // Mixed Content with Entities and HTML
            [
                '<h1>Title &amp; Subtitle</h1><p>Content with &lt;code&gt; examples</p>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Title & Subtitle Content with <code> examples',
            ],

            // Unicode and Special Characters
            [
                '<p>Unicode: café, naïve, résumé</p>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Unicode: café, naïve, résumé',
            ],
            [
                'Emoji test: 🚀 🎉 ❤️',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Emoji test: 🚀 🎉 ❤️',
            ],

            // Comments
            [
                '<!-- HTML comment -->Content<!-- Another comment -->',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Content',
            ],

            // Multiline HTML
            [
                '<a href="https://example.com"
                   target="_blank"
                   rel="noopener"
                   title="Example Link">
                    Link text with newlines
                </a>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Link text with newlines',
            ],

            // Custom HTML Elements
            [
                '<my-component data-prop="value">Component content</my-component>',
                BaseBuilder::INDEX_STRATEGY_HTML,
                'Component content',
            ],

            // Test plain text strategy
            [
                'Plain text content',
                BaseBuilder::INDEX_STRATEGY_TEXT, // assuming this constant exists
                'Plain text content',
            ],
            [
                'Text   with    multiple   spaces',
                BaseBuilder::INDEX_STRATEGY_TEXT,
                'Text with multiple spaces',
            ],

            // Large content blocks
            [
                str_repeat('<p>Repeated content </p>', 100),
                BaseBuilder::INDEX_STRATEGY_HTML,
                trim(str_repeat('Repeated content ', 100)),
            ],
        ];
    }
}
