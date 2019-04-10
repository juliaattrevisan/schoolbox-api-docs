<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator;

use InvalidArgumentException;
use Schoolbox\Filter;
use function sprintf;

/**
 * @see Factory for this filter's description.
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Capability/Indicator usage';

    public const KEY_CAPABILITIES = 'capabilities';
    public const KEY_INDICATORS   = 'indicators';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_CAPABILITIES,
            self::KEY_INDICATORS,
        ];
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_CAPABILITIES === $key) {
            return 'Folder Capabilities';
        }
        if (self::KEY_INDICATORS === $key) {
            return 'Folder Indicators';
        }
        throw new InvalidArgumentException(sprintf("Unknown '%s' key: '%s'", self::NAME, $key));
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        // No options checked
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability(string $customAbstractKey, Filter\Page\Model $pageFilter): bool
    {
        // by default, all options are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/CapabilityIndicator/templates';
    }
}
