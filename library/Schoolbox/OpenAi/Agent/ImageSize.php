<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent;

/**
 * Enum for the image generator agent's allowed sizes.
 *
 * @see https://platform.openai.com/docs/guides/image-generation?image-generation-model=gpt-image-1#size-and-quality-options
 */
enum ImageSize: string
{
    case Auto      = 'auto';
    case Square    = '1024x1024';
    case Landscape = '1536x1024';
    case Portrait  = '1024x1536';

    /**
     * Create a human-readable label for the image size.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto      => 'Auto',
            self::Square    => 'Square (1x1)',
            self::Landscape => 'Landscape (3x2)',
            self::Portrait  => 'Portrait (2x3)',
        };
    }
}
