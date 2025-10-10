<?php
/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent;

/**
 * Enum for the image generator agent's allowed styles.
 */
enum ImageStyle: string
{
    case Background     = 'background';
    case Cartoon        = 'cartoon';
    case OilPainting    = 'oil_painting';
    case Photorealistic = 'photo_realistic';
    case PopArt         = 'pop_art';
    case Sketch         = 'sketch';
    case Surrealism     = 'surrealism';
    case WideAngle      = 'wide_angle';
    case VectorArt      = 'vector_art';

    /**
     * Create a human-readable label for the image style.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Background     => 'Background',
            self::Cartoon        => 'Cartoon',
            self::OilPainting    => 'Oil Painting',
            self::Photorealistic => 'Photorealistic',
            self::PopArt         => 'Pop Art',
            self::Sketch         => 'Sketch',
            self::Surrealism     => 'Surrealism',
            self::WideAngle      => 'Wide Angle',
            self::VectorArt      => 'Vector Art',
        };
    }
}
