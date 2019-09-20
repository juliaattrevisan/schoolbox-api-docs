<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use RangeException;

abstract class AbstractMarkScheme implements IMarkScheme
{
    public const SLUG = 'raw';

    const TITLE = 'Abstract Mark Scheme';

    /**
     * Does this mark scheme produce human-readable results that are comparable
     * as numbers?
     *
     * @var bool
     */
    const QUANTITATIVE = false;

    /**
     * Bounds checks a normalised mark
     *
     * @param float $normalisedMark
     * @throws RangeException If $normalisedMark is outside 0.0 to 100.0
     * @throws \InvalidArgumentException  If $normalisedMark isn't a numeric type
     */
    protected function validateNormalisedMark($normalisedMark)
    {
        $this->validateNormalisedMarkType($normalisedMark);
        $this->validateNormalisedMarkValue($normalisedMark);
    }

    /**
     * Filters normalised mark to between its min and max value.
     *
     * @param float|null $normalisedMark
     * @return float|null null if $normalisedMark is null
     */
    protected function filterNormalisedMark($normalisedMark)
    {
        if (null === $normalisedMark) {
            return null;
        }
        return min(self::NORMALISED_MARK_MAX, max(self::NORMALISED_MARK_MIN, $normalisedMark));
    }

    /**
     * Validates normalised mark type
     *
     * @param float|int $normalisedMark
     * @throws \InvalidArgumentException If $normalisedMark isn't a numeric type
     */
    protected function validateNormalisedMarkType($normalisedMark)
    {
        if (!(is_float($normalisedMark) || is_int($normalisedMark))) {
            $type = gettype($normalisedMark);
            $msg = "Normalised mark type must be float or int: $normalisedMark ($type)";
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * Validates normalised mark value
     *
     * @param float $normalisedMark
     * @throws \RangeException if $normalisedMark isn't between min/max
     */
    protected function validateNormalisedMarkValue($normalisedMark)
    {
        if ($normalisedMark < IMarkScheme::NORMALISED_MARK_MIN || $normalisedMark > IMarkScheme::NORMALISED_MARK_MAX) {
            $msg = 'Normalised mark must lie between '.IMarkScheme::NORMALISED_MARK_MIN.
                ' and '.IMarkScheme::NORMALISED_MARK_MAX.' inclusive: '.$normalisedMark;
            throw new RangeException($msg);
        }
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return static::TITLE;
    }

    /**
     * @return bool
     */
    public function isQuantitative()
    {
        return static::QUANTITATIVE;
    }

    /**
     * @return bool
     */
    public function isQualitative()
    {
        return !static::QUANTITATIVE;
    }

    /**
     * @inheritdoc
     */
    public function generateContext(): array
    {
        return [
            'mode' => static::SLUG,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getValueForJS($mark, ?float $maxValue)
    {
        return $mark;
    }
}
