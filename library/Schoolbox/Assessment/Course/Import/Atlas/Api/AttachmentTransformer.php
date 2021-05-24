<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Karriere\JsonDecoder\Bindings\AliasBinding;
use Karriere\JsonDecoder\ClassBindings;
use Karriere\JsonDecoder\Transformer;

/**
 * Transformer definition for JSON curriculum responses to PHP objects.
 */
class AttachmentTransformer implements Transformer
{
    /**
     * Register bindings for this class.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param ClassBindings $classBindings
     */
    public function register(ClassBindings $classBindings): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new AliasBinding('sortOrder', 'SortOrder'));

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new AliasBinding('attachmentType', 'AttachmentType'));

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new AliasBinding('itemType', 'ItemType'));

        /** @noinspection PhpUnhandledExceptionInspection */
        $classBindings->register(new AliasBinding('itemId', 'ItemID'));
    }

    /**
     * @return string
     */
    public function transforms(): string
    {
        return Attachment::class;
    }
}
