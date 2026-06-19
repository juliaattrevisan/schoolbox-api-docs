<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

use JsonSerializable;

/**
 * A supporting excerpt relating to a specific concern category.
 */
class Evidence implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param string $quote           A short exact excerpt from the interaction.
     * @param string $messagePosition Where the quote came from in the interaction.
     * @param string $whyItMatters    A short explanation of why this quote supports the concern.
     */
    public function __construct(
        private string $quote,
        private string $messagePosition,
        private string $whyItMatters,
    ) {
    }

    /**
     * @return string
     */
    public function getQuote(): string
    {
        return $this->quote;
    }

    /**
     * @return string
     */
    public function getMessagePosition(): string
    {
        return $this->messagePosition;
    }

    /**
     * @return string
     */
    public function getWhyItMatters(): string
    {
        return $this->whyItMatters;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'quote' => $this->quote,
            'message_position' => $this->messagePosition,
            'why_it_matters' => $this->whyItMatters,
        ];
    }

    /**
     * Build an evidence item from an array.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            quote: (string)($data['quote'] ?? ''),
            messagePosition: (string)($data['message_position'] ?? ''),
            whyItMatters: (string)($data['why_it_matters'] ?? ''),
        );
    }
}
