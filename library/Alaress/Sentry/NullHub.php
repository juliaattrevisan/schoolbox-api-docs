<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Sentry\Breadcrumb;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Integration\IntegrationInterface;
use Sentry\MonitorConfig;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * Sentry hub implementation, which logs nothing anywhere.
 */
class NullHub implements HubInterface
{
    /**
     * @inheritDoc
     */
    public function getClient(): ?ClientInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getLastEventId(): ?EventId
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function pushScope(): Scope
    {
        return new Scope();
    }

    /**
     * @inheritDoc
     */
    public function popScope(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function withScope(callable $callback)
    {
        $scope = $this->pushScope();
        try {
            return $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    /**
     * @inheritDoc
     */
    public function configureScope(callable $callback): void
    {
    }

    /**
     * @inheritDoc
     */
    public function bindClient(ClientInterface $client): void
    {
    }

    /**
     * @inheritDoc
     */
    public function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function captureLastError(?EventHint $hint = null): ?EventId
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        return new Transaction($context, $this);
    }

    /**
     * @inheritDoc
     */
    public function getTransaction(): ?Transaction
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSpan(): ?Span
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function setSpan(?Span $span): HubInterface
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function captureCheckIn(
        string $slug,
        CheckInStatus $status,
        $duration = null,
        ?MonitorConfig $monitorConfig = null,
        ?string $checkInId = null,
    ): ?string {
        return $checkInId;
    }
}
