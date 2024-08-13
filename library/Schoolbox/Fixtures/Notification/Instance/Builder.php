<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Notification\Instance;

use Alaress\Async\Notification\Instance\Builder as InternalBuilder;
use Alaress\Async\Notification\Instance\BuilderInterface;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\User as User;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Team\Model as Team;

/**
 * Sports fixtures customized wrapper for the notification instance builder.
 *
 * @package Schoolbox\Fixtures\Notification\Instance
 */
class Builder implements BuilderInterface
{
    /**
     * Internal builder instance that this class wraps around.
     *
     * @var InternalBuilder
     */
    protected $internalBuilder;

    public function __construct(User $effector, $validate = true)
    {
        $this->internalBuilder = new InternalBuilder($validate);
        // Set some defaults into the builder
        $this->internalBuilder
            ->setIcon("/portrait.php?id={$effector->getId()}&size=square192")
            ->setSeverity(Logger::INFO);
    }

    /**
     * @param Sport $sport
     * @param int $stacking
     * @return self
     */
    public function setSportParam(Sport $sport, $stacking = Param::STACKING_REPLACE)
    {
        $this->internalBuilder->setParam('sport', new Param($sport->getName(), $sport->getUrl(), $stacking));
        return $this;
    }

    /**
     * @param string $metaName
     * @param string $metaValue
     * @return self
     */
    public function putMatchMeta(string $metaName, string $metaValue)
    {
        $this->internalBuilder->putMeta($metaName, $metaValue);
        return $this;
    }

    /**
     * @param Matchup $matchup
     * @param Team $team
     * @return self
     */
    public function setMatchHref(Matchup $matchup, Team $team)
    {
        $this->setHref($this->buildHrefForMatch($matchup, $team));
        return $this;
    }

    /**
     * @param Matchup $matchup
     * @param Team $team
     * @param int $stacking
     * @return self
     */
    public function setMatchParam(Matchup $matchup, Team $team, $stacking = Param::STACKING_REPLACE)
    {
        $this->internalBuilder->setParam('match', $this->buildMatchParam($matchup, $team, $stacking));
        return $this;
    }

    /**
     * Builds the href for the results page for this match and team.
     *
     * @param Matchup $matchup
     * @param Team $team
     * @return string
     */
    public function buildHrefForMatch(Matchup $matchup, Team $team)
    {
        return "/fixtures/matches/{$matchup->getId()}/results/{$team->getId()}";
    }

    /**
     * Builds a parameter for this notifier's builder's "match" parameter.
     *
     * @param Matchup $matchup
     * @param Team $team
     * @param int $stacking
     * @return Param
     */
    public function buildMatchParam(Matchup $matchup, Team $team, $stacking = Param::STACKING_REPLACE)
    {
        $ts   = microtime(true);
        $href = $this->buildHrefForMatch($matchup, $team);
        return new Param($matchup->getName(), "{$href}?{$ts}", $stacking);
    }

    /** Pass-through to internal builder's setters */

    /**
     * @param string             $token
     * @param ParameterInterface $param
     * @return self
     */
    public function setParam($token, ParameterInterface $param)
    {
        $this->internalBuilder = $this->internalBuilder->setParam($token, $param);
        return $this;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->internalBuilder = $this->internalBuilder->setTitle($title);
        return $this;
    }

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon)
    {
        $this->internalBuilder = $this->internalBuilder->setIcon($icon);
        return $this;
    }

    /**
     * @param int $timestamp
     * @return self
     */
    public function setTimestamp($timestamp)
    {
        $this->internalBuilder = $this->internalBuilder->setTimestamp($timestamp);
        return $this;
    }

    /**
     * @param int $severity
     * @return self
     */
    public function setSeverity($severity)
    {
        $this->internalBuilder = $this->internalBuilder->setSeverity($severity);
        return $this;
    }

    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->internalBuilder = $this->internalBuilder->setNamespace($namespace);
        return $this;
    }

    /**
     * @param string $href
     * @return self
     */
    public function setHref($href)
    {
        $this->internalBuilder = $this->internalBuilder->setHref($href);
        return $this;
    }

    /** Interface methods */

    /**
     * @inheritdoc
     */
    public function setTopics($topics)
    {
        $this->internalBuilder = $this->internalBuilder->setTopics($topics);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function build()
    {
        return $this->internalBuilder->build();
    }
}
