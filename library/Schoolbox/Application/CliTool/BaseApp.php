<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionResult;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;

/**
 * New base CLI app, which uses a new library for setting command-line options
 * and description.
 */
abstract class BaseApp extends App
{
    // Command-line options common to all apps.

    /**
     * Long and short options for outputting help.
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    /**
     * Long and short options for perform mode.
     */
    private const OPT_SHORT_PERFORM = 'p';
    private const OPT_LONG_PERFORM = 'perform';

    // Command line options handled at the kernel level, rather than at the app:
    // duplicated here for help text.

    /**
     * Should log items be formatted for text file rather than terminal?
     *
     * If set, causes severity level (as per the values of the `\Monolog\Level`
     * enum) to be prefixed to field value, so that logs may be made parsable by
     * scripts:
     * * without: '[!][2019-02-26 00:29:22.72907][13746]'
     * * with:    '[!][@550][2019-02-26 00:29:22.72907][pid:13746]'.
     */
    private const OPT_LONG_PORCELAIN = 'porcelain';

    /**
     * What level of log messages should be output?
     *
     * May be provided more than once:
     * * not provided: `\Monolog\Level::Error`
     * * -v:           `\Monolog\Level::Notice`
     * * -vv:          `\Monolog\Level::Info`
     * * -vvv:         `\Monolog\Level::Debug`
     *
     * May be set to a minimum higher level by providing the
     * `\Schoolbox\Application\CliKernelAbstract::OPTION_LEVEL` value
     * (as a `\Monolog\Level` enum value) to the
     * `\Schoolbox\Application\CliTool\Kernel` constructor.
     */
    private const OPT_SHORT_VERBOSITY = 'v';

    /**
     * Collection of command-line options
     */
    protected OptionCollection $optionCollection;

    /**
     * The actual parsed result
     */
    protected OptionResult $optionResult;

    /**
     * Overridden to allow the option parser to be set up.
     *
     * @inheritdoc
     */
    public function setup(array $appOptions, ContainerInterface $di, $outStream, LoggerInterface $logger, bool $perform): App
    {
        parent::setup($appOptions, $di, $outStream, $logger, $perform);

        // Set up command-line options common to all scripts
        $this->optionCollection = new OptionCollection();
        $this->optionCollection->add(
            self::OPT_SHORT_HELP . '|' . self::OPT_LONG_HELP,
            'Display help text and exit',
        );
        if ($this->hasPerformMode()) {
            $this->optionCollection->add(
                self::OPT_SHORT_PERFORM . '|' . self::OPT_LONG_PERFORM,
                'Perform mode: if not provided, no changes will be written',
            );
        }
        $this->optionCollection->add(
            self::OPT_SHORT_VERBOSITY,
            'Verbosity (up to 3 times)',
        )->incremental();
        $this->assignDeprecatedOptions();

        // ... including an esoteric option I forgot existed
        $this->optionCollection->add(self::OPT_LONG_PORCELAIN, 'Porcelain log output');

        $this->setOptions();
        $this->optionResult = new OptionParser($this->optionCollection)->parse($_SERVER['argv']);

        return $this;
    }

    /**
     * Output help text and exit, if and only if the `-h` or `--help` options
     * were provided.
     *
     * @return void|never-return
     */
    public function outputHelpIfOptionProvided(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP)) {
            $printer = new HelpPrinter($this->getName(), $this->getDescription());
            echo $printer->render($this->optionCollection);
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }
    }

    /**
     * Assign options from the now-deprecated `getShortOpts` and `getLongOpts`
     * methods.
     */
    private function assignDeprecatedOptions(): void
    {
        // Extract app options, exc. help
        $shortOptions = $this->getShortOpts();
        unset($shortOptions[self::OPT_SHORT_HELP]);
        if (!empty($shortOptions)) {
            trigger_error(
                'Setting options via getShortOpts is deprecated: all options should be set in setOptions instead',
                E_USER_DEPRECATED,
            );
        }
        foreach ($shortOptions as $shortOption) {
            // What type of option is this?
            // No value, mandatory value, optional value?
            if (str_ends_with($shortOption, '::')) {
                $shortOption = rtrim($shortOption, ':') . '?';
            }
            $this->optionCollection->add($shortOption, 'Undocumented short option: please set in setOptions instead');
        }

        $longOptions = $this->getLongOpts();
        unset($longOptions[self::OPT_LONG_HELP]);
        if (!empty($longOptions)) {
            trigger_error(
                'Setting options via getLongOpts is deprecated: all options should be set in setOptions instead',
                E_USER_DEPRECATED,
            );
        }
        foreach ($longOptions as $longOption) {
            if (str_ends_with($longOption, '::')) {
                $longOption = rtrim($longOption, ':') . '?';
            }
            $this->optionCollection->add($longOption, 'Undocumented long option: please set in setOptions instead');
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
     * * -h, --help:    show help and exit
     * * -v:            verbosity (may be provided multiple times)
     * * -p, --perform: perform mode
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

    /**
     * Overridden to look up from option parser.
     *
     * @return bool
     */
    protected function perform(): bool
    {
        if (!$this->hasPerformMode()) {
            throw new \LogicException('This application does not have a perform mode.');
        }
        return $this->optionResult->get('p');
    }

    /**
     * Overrides `getOpt` to use new option parser instead.
     *
     * @param string $name
     * @return array|bool|mixed|null
     */
    protected function getOpt(string $name)
    {
        // Old options might have the : suffix: if so, remove it
        $trimmedName = rtrim($name, ':');

        // Option `$name` might have been defined as:
        // * a single option only
        // * a short option within a short|long pair
        // * a long option within a short|long pair
        // Need to handle all of these cases
        $option = $this->optionCollection->get($trimmedName);
        if ($option instanceof Option) {
            if (isset($option->long)) {
                return $this->optionResult->get($option->long);
            }
            if (isset($option->short)) {
                return $this->optionResult->get($option->short);
            }
        }

        // Otherwise return nothing
        return null;
    }
}
