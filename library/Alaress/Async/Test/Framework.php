<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Test;

use Aura\Di;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit_Framework_AssertionFailedError;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use UnderflowException;

class Framework
{
    /** @var LoggerInterface  */
    protected $log;
    /** @var LoopInterface  */
    protected $loop;
    /** @var Di\Container  */
    protected $di;
    /** @var string  */
    protected $service;
    /** @var string  */
    protected $logdir;
    /** @var string  */
    protected $rootDir;
    /** @var array  */
    protected $subscriptions = [];
    /** @var array */
    protected $daemonInstances = [];
    /** @var array */
    protected $hooks = [];


    /**
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param string|null     $env
     * @param string|null     $logDir
     * @param string          $rootDir
     */
    public function __construct($di, $loop, $log, string $env = null, string $logDir = null, $rootDir = SITE_root)
    {
        $this->di = $di;
        $this->loop = $loop;
        $this->log = $log;
        $this->rootDir = $rootDir;

        // on dev servers will be "schoolboxdev"
        $this->service = empty($env) ? 'schoolbox' : $env;

        // obtain absolute directory reference
        $this->logdir  = realpath(empty($logDir) ? $rootDir . '/data' : $logDir);
    }


    /**
     * With any luck this will get called even if the framework blows up
     *
     * @return void
     */
    public function __destruct()
    {
        foreach (array_keys($this->daemonInstances) as $name) {
            // kill it
            try {
                $this->slayDaemon($name);
            } catch (\Exception $e) {
                // noop - destructors must not throw exceptions
            }
        }
        // and finish off with removing this altogether
        unset ($this->daemonInstances);
    }


    /**
     * Ensure the target log directory exists
     *
     * @return string
     */
    public function ensureDirectories()
    {
        return `mkdir -p {$this->logdir}`;
    }


    /**
     * Invoke Buffy
     *
     * @return void
     */
    public function freezeHell()
    {
        // localise because ceebs
        $log = $this->log;
        $service = $this->service;

        $this->log->warning("Freezing hell over");

        foreach (['schedule','route','socket','digest','bell'] as $d) {
            $killswitch = "service $service-{$d}d stop 2>&1";
            $log->debug("Stop $d daemon: " . trim(`$killswitch`));
        }

        $log->info("All daemons stopped.", [ 'char' => '-' ]);
    }


    /**
     * @throws UnderflowException
     * @throws OutOfBoundsException
     * @return string
     */
    public function promptForTest()
    {
        $tests = glob($this->rootDir . '/test/notif/tests/test*.php');

        if (count($tests) == 0) {
            throw new UnderflowException("No tests available to run.");
        }

        $prompt  = "Choose a test to run: (alternatively you can specify this via --testcase={classname})\n\n";

        $classes = array_map(
            function ($file)
            {
                if (preg_match('`test([^/]*)\.php$`', $file, $matches)) {
                    return $matches[1];
                }
                return null;
            },
            $tests
        );

        $prompt .= implode("\n", array_map(
            function ($k, $v)
            {
                if ($v) {
                    return "$k) $v";
                }
                return '';
            },
            array_keys($classes),
            array_values($classes)
        ));

        $prompt .= "\n\nSelect option ";

        if (count($tests) == 1) {
            $prompt .= "[0]";
        } else {
            $prompt .= "[0-" . (count($tests) - 1) . ']';
        }

        echo "$prompt: ";
        $option = readline();

        if (!(preg_match('`\d+`', $option) && $option >= 0 && $option < count($tests))) {
            throw new OutOfBoundsException("Invalid option ($option) specified; aborting.");
        }

        $testClass = $classes[$option];

        if (empty($testClass)) {
            throw new OutOfBoundsException("Invalid option ($option) specified; aborting.");
        }

        return $testClass;
    }


    /**
     * @param string $testClass
     * @return TestCase
     */
    public function buildTestCase($testClass)
    {
        $log = $this->log;
        $testFilename = $this->rootDir . "/test/notif/tests/test$testClass.php";

        if (!is_readable($testFilename)) {
            throw new InvalidArgumentException("Invalid test specified; file '$testFilename' is not readable.");
        }

        $log->debug("Loading $testFilename");
        require_once $testFilename;

        $log->debug("Targeting class \\$testClass");
        $rc = new ReflectionClass($testClass);

        if (!$rc->implementsInterface(TestCase::class)) {
            throw new InvalidArgumentException("Test case '$testClass' does not implement Alaress\\Async\\Test\\TestCase, cannot proceed");
        }

        /** @var TestCase $testCase */
        $testCase = $rc->newInstance();

        // attach our logger
        $testCase->attachLogger($log);
        $testCase->prepare();

        // scan for listeners
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // reset
            $conditions = [];

            // invoke methods that begin with 'on'
            if (strpos($method->getName(), 'on') !== 0) {
                continue;
            }

            // filter out methods that lack appropriate annotations
            $prefix = implode('|', $testCase->requiredDaemons());
            $regex  = '`@(' . $prefix . ')\s+([^\n]+)\n`';

            if (!preg_match_all($regex, $method->getDocComment(), $matches)) {
                continue;
            }

            for ($i = 0; $i < count($matches[0]); ++$i) {
                $daemon = $matches[1][$i];
                $regex  = $this->quoteAndEscape($matches[2][$i]);

                if (empty($conditions[$daemon][$regex])) {
                    $conditions[$daemon][$regex] = 0;
                }

                $conditions[$daemon][$regex]++;
            }

            if (empty($conditions)) {
                continue;
            }

            // accommodate the fact that we might be running multiple tests in parallewl
            $this->subscriptions[$testClass][] = new Subscription($conditions, $testCase, $method);

            $log->debug("Listening for $testClass::{$method->getName()}");
        }

        $daemons  = $testCase->requiredDaemons();

        // check we actually have daemons to invoke
        if (!is_array($daemons) || count($daemons) == 0) {
            throw new InvalidArgumentException("No daemons specified for testing, nothing to do.");
        }

        // ok, the gauntlet has been run. We're good to test.
        return $testCase;
    }


    /**
     * @param TestCase $testCase
     * @throws InvalidArgumentException
     * @return void
     */
    public function launch(TestCase $testCase)
    {
        $log = $this->log;

        // get your workers up and running
        foreach ($testCase->requiredDaemons() as $name) {
            $log->debug("Spinning up an instance of $name");

            try {
                // we don't actually need to do anything with these, we just need to maintain references
                // to them so they don't close themselves
                $this->daemonInstances[$name] = $this->spawnDaemon($name);

                $log->info("$name launched", [ 'char' => '+' ]);

            } catch (RuntimeException $e) {
                $log->critical("Could not launch $name; aborting test");
                return;
            }
        }

        // it's dangerous to go alone - take this
        $testCase->setBuffy(function () {
            foreach (array_keys($this->daemonInstances) as $name) {
                // face your daemons
                $this->slayDaemon($name);
            }
            // now they're out of the picture, we can stop
            $this->loop->stop();
        });
    }


    /**
     * Yikes... this is hectic
     *
     * @param string $string
     * @param string $char
     * @return string
     */
    protected function quoteAndEscape($string, $char = '`')
    {
        $d = $char == '#' ? '`' : '#';

        $pattern = "$d\\[^\\\\]$char$d";
        $replace = "\\$char";

        return $char . preg_replace($pattern, $replace, $string) . $char;
    }


    /**
     * Spin up an instance of the given daemon
     *
     * @param string $name
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return array
     */
    protected function spawnDaemon($name)
    {
        $root   = $this->rootDir . '/tools';
        $logdir = $this->logdir;

        $map = [
            TestCase::DAEMON_SCHEDULE => "$root/mq/scheduled.php",
            TestCase::DAEMON_ROUTE    => "$root/notif/route/routed.php",
            TestCase::DAEMON_DIGEST   => "$root/digest/digestd.php",
            TestCase::DAEMON_SOCKET   => "$root/socket/socketd.php"
        ];

        if (!isset($map[$name])) {
            throw new InvalidArgumentException("Invalid daemon requested: $name");
        }

        $target = $map[$name];

        $cmd = "$target -vvv --porcelain | tee -a $logdir/$name.log";

        // open pipes
        $ds = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'file', "$logdir/$name-error.log", 'a' ]
        ];

        $process = proc_open($cmd, $ds, $pipes, $this->rootDir);

        if (!is_resource($process)) {
            throw new RuntimeException("Failed to launch daemon process for $name");
        }

        $this->loop->addReadStream($pipes[1], function ($stream) use ($name) {
            $this->onRead($stream, $name);
        });

        return [ $process, $pipes ];
    }


    /**
     * @param string $name  the daemon to slay
     * @return int          child process exit code
     */
    protected function slayDaemon($name)
    {
        // the sequence here is important - we want to de-register the
        // daemon BEFORE we actually kill it in case it dodges the blow
        [$pp, $pipes] = $this->daemonInstances[$name];
        unset ($this->daemonInstances[$name]);

        $status = proc_get_status($pp);

        if ($status['running']) {
            foreach ($pipes as $pipe) {
                // SILENCE, FOUL BEAST!
                fclose($pipe);
            }

            // still running: recursively terminate the ancestry
            $this->terminateProcess($status['pid']);
        }

        // now we can close the pointer
        return proc_close($pp);
    }


    /**
     * @param int $ppid  the process ID to terminate
     * @return void
     */
    private function terminateProcess($ppid)
    {
        // check for children
        $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $ppid`);

        foreach ($pids as $pid) {
            if (is_numeric($pid)) {
                // recurse into child looking for deeper process nesting
                $this->terminateProcess($pid);
            }
        }

        // terminate
        posix_kill($ppid, SIGKILL);
    }


    /**
     * @param resource $stream
     * @param string   $name
     */
    protected function onRead($stream, $name)
    {
        $log  = $this->log;
        $line = fgets($stream);
        $apos = strpos($line, ' => ');

        if ($apos === false) {
            return;
        }

        $line = trim(substr($line, $apos + 4));

        $record = sprintf("%-9s -> %s", $name, $line);

        $match = false;

        foreach ($this->subscriptions as $testClass => $subscriptions) {
            /** @var Subscription $subscription */
            foreach ($subscriptions as $subscription) {
                $match = $match || $this->testSubscription($subscription, $name, $line, $record, $testClass);
            }
        }

        if (!$match) {
            // just log it if we didn't already
            $log->debug($record);
        }
    }


    /**
     * @param Subscription $subscription
     * @param              $name
     * @param              $line
     * @param              $record
     * @param              $testClass
     * @return bool
     */
    protected function testSubscription(Subscription $subscription, $name, $line, $record, $testClass)
    {
        $log = $this->log;

        foreach ($subscription->getConditions() as $daemonName => $regex) {
            if ($name != $daemonName) {
                continue;
            }

            foreach ($regex as $pattern => $count) {
                if (preg_match($pattern, $line, $captures)) {

                    // remove the full string capture
                    array_shift($captures);

                    $callbackName = $subscription->getCallbackName();

                    $log->info("$record [match $callbackName]", [ 'char' => '?' ]);

                    // hook into that
                    $hookKey = "$daemonName:$pattern";

                    if (empty($this->hooks[$testClass][$callbackName][$hookKey])) {
                        // doesn't yet exist
                        $this->hooks[$testClass][$callbackName][$hookKey] = [];
                    }

                    // increment the counter
                    $this->hooks[$testClass][$callbackName][$hookKey][] = $captures;

                    try {
                        // attempt to fire this subscription; if the preconditions are not met it won't fire
                        $unhook = $subscription->fire($this->hooks[$testClass][$callbackName]);

                    } catch (AssertionFailedError $e) {
                        // it failed assertions, but it *did* fire, so we can unhook
                        $unhook = true;

                        // handle the exception
                        $this->log->warning($e->getMessage(), [ 'exception'=>$e ]);

                    } catch (\Exception $e) {
                        // we can't exceptions exploding our harness; this is one of those rare cases you need to catch EVERYTHING
                        $unhook = true;

                        $this->log->critical($e->getMessage(), [ 'exception'=>$e ]);
                    }

                    if ($unhook !== false) {
                        // it fired: unset the hooks so we can go again
                        // TODO consider unwinding this as a stack rather than blindly unsetting
                        unset($this->hooks[$testClass][$callbackName]);
                    }

                    // full break
                    return true;
                }
            }

        }

        return false;
    }
}
