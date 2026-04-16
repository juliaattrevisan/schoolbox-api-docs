<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Format;

use InvalidArgumentException;
use Schoolbox\Editor\OpenAI\Log\LogFormat;

/**
 * Responsible for enumeration of the IStrategy interface
 * @see IStrategy
 */
class StrategyFactory
{
    /**
     * Cache the strategies so repeated calls to make() for the same strat will return the same instance

     * @var array<string,IStrategy>
     */
    private array $strategyCache = [];

    /**
     * @param LogFormat $logFormat
     * @return IStrategy
     */
    public function makeStrategy(LogFormat $logFormat): IStrategy
    {
        $key = $logFormat->value;
        if (!\array_key_exists($key, $this->strategyCache)) {
            $this->strategyCache[$key] = match ($logFormat) {
                AgentChat::getLogFormat() => new AgentChat(),
                Guardrail::getLogFormat() => new Guardrail(),
                default => throw new InvalidArgumentException("Unknown log format: {$logFormat->value}"),
            };
        }
        return $this->strategyCache[$key];
    }
}
