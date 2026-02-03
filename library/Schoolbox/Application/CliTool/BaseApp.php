<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use GetOpt;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;

/**
 * New base CLI app, which uses a new library for setting command-line options
 * and description.
 */
abstract class BaseApp extends App
{
    /**
     * Command-line option parser
     */
    protected GetOpt\GetOpt $optionParser;

    /**
     * Overridden to allow the option parser to be set up.
     *
     * @inheritdoc
     */
    public function setup(array $appOptions, ContainerInterface $di, $outStream, LoggerInterface $logger, bool $perform): App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);

        // Set up the CLI argument parser
        $this->optionParser = new GetOpt\GetOpt()
            ->set(GetOpt\GetOpt::SETTING_STRICT_OPERANDS, true);
        $help = new Help()
            ->set(Help::NAME, $this->getName())
            ->set(Help::DESCRIPTION, $this->getDescription());
        $this->optionParser->setHelp($help);

        // Set up command-line options
        $this->assignDeprecatedOptions();
        if ($this->hasPerformMode()) {
            $this->optionParser = $this->optionParser->addOption(
                new GetOpt\Option('p', 'perform', GetOpt\GetOpt::NO_ARGUMENT)
                    ->setDescription('Perform mode')
            );
        }
        $this->setOptions();
        return $this;
    }

    /**
     * Assign options from the now-deprecated `getShortOpts` and `getLongOpts`
     * methods.
     */
    private function assignDeprecatedOptions(): void
    {
        // Extract app options, exc. help
        $shortOptions = $this->getShortOpts();
        unset($shortOptions['h']);
        if (!empty($shortOptions)) {
            trigger_error(
                'Setting options via getShortOpts is deprecated: all options should be set in setOptions instead',
                E_USER_DEPRECATED,
            );
        }
        foreach ($shortOptions as $shortOption) {
            // What type of option is this?
            // No value, mandatory value, optional value?
            $hasOptions = str_ends_with($shortOption, ':');
            $this->optionParser = $this->optionParser->addOption(
                new GetOpt\Option(
                    rtrim($shortOption, ':'),
                    null,
                    $hasOptions ? GetOpt\GetOpt::REQUIRED_ARGUMENT : GetOpt\GetOpt::NO_ARGUMENT,
                )
            );
        }

        $longOptions = $this->getLongOpts();
        unset($longOptions['help']);
        if (!empty($longOptions)) {
            trigger_error(
                'Setting options via getLongOpts is deprecated: all options should be set in setOptions instead',
                E_USER_DEPRECATED,
            );
        }
        foreach ($longOptions as $longOption) {
            $hasOptions = str_ends_with($longOption, ':');
            $this->optionParser = $this->optionParser->addOption(
                new GetOpt\Option(
                    null,
                    rtrim($longOption, ':'),
                    $hasOptions ? GetOpt\GetOpt::REQUIRED_ARGUMENT : GetOpt\GetOpt::NO_ARGUMENT,
                )
            );
        }
    }

    /**
     * Get this application's name.
     *
     * (Abstract as the application must have a name.)
     *
     * @return string
     */
    abstract protected function getName(): string;

    /**
     * Get this application's name.
     *
     * (Abstract as the application must be described.)
     *
     * @return string
     */
    abstract protected function getDescription(): string;

    /**
     * Set this application's options, in addition to the following implicit
     * options:
     * * -h, --help: show help and exit
     * * -V:         show version and exit
     * * -v:         verbosity (may be provided multiple times)
     * * -p, --perform:
     *
     * @return self
     */
    abstract protected function setOptions(): self;

    /**
     * Does this app support perform mode?
     *
     * (Abstract as this question must be considered.)
     *
     * @return bool
     */
    abstract protected function hasPerformMode(): bool;

    protected function perform(): bool
    {
        if (!$this->hasPerformMode()) {
            throw new \LogicException('This application does not have a perform mode.');
        }
        return $this->optionParser->get('p');
    }
}
