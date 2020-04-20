<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Config;

use Aura\Di\Exception\ServiceNotFound;
use Etherpad\PadMapper;
use LogicException;
use PDOException;
use Predis;
use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use UnderflowException;
use UnexpectedValueException;
use function array_merge;
use function defined;
use function dir;
use function exec;
use function implode;
use function opendir;
use function passthru;
use function preg_match;
use function preg_replace;
use function realpath;
use function rename;
use function sprintf;
use const DIRECTORY_SEPARATOR;
use const INSTANCE_ID;

/**
 * A CLI tool for updating a school's instance ID.
 *
 * Probably won't work in CLOUD, where the conf file is managed/structured differently.
 *
 * This tool is likely buggy. If you're going to use it on a production instance, warn the client they may
 * loose ephemeral data.
 *
 * TODO:
 * * Canvas DB Name contains the INSTANCE_ID
 */
class InstanceIdManager extends CliTool\App
{
    private const OPT_LONG_INSTANCE_ID_NEW = 'new-instance-id:';

    /** @var Predis\ClientInterface $redis */
    private $redis;

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return array_merge(parent::getLongOpts(), [
            self::OPT_LONG_INSTANCE_ID_NEW,
        ]);
    }


    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        // We need to restart daemons, much easier as root, otherwise need auth complications.
        return true;
    }


    /**
     * @throws LogicException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws MapperException
     * @throws UnderflowException
     */
    public function run(): void
    {
        $this->redis = $this->getDi()->get('redis');

        $newInstanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID_NEW);
        if (($newInstanceId ?? '') === '') {
            $this->getLogger()->error(sprintf("You must specify a new instance id using the --%s flag", self::OPT_LONG_INSTANCE_ID_NEW));
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        $this->getLogger()->notice("New INSTANCE_ID = '$newInstanceId'");

        $oldInstanceId = defined('INSTANCE_ID') ? INSTANCE_ID : '';
        if ($oldInstanceId === $newInstanceId) {
            $this->getLogger()->notice("New instance id == Old instance id ('{$newInstanceId}'), nothing to do.");
//            return;
        }

        $this->updateConfFile($oldInstanceId, $newInstanceId);
//        $this->updateRedisKeys($oldInstanceId, $newInstanceId);
//        $this->updateEtherpadEnvironments($oldInstanceId, $newInstanceId);
//        $this->renameLockFiles($oldInstanceId, $newInstanceId);
//        $this->renameMessageQueues($oldInstanceId, $newInstanceId);
//        $this->rebuildSkin();
        $this->rebuildSubscriptionCache($newInstanceId);
//        $this->restartDaemons();
    }


    /**
     * Updates the instance ID in the conf file
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     * @throws LogicException
     */
    private function updateConfFile(string $oldInstanceId, string $newInstanceId): void
    {
        $confFile = __DIR__ . '/../../../www/conf/conf.php';
        $confFile = realpath($confFile);

        $command = "sed -i -E \"s/^\s*define\(['\\\"]INSTANCE_ID['\\\"],\s*['\\\"]{$oldInstanceId}['\\\"]\);/    define('INSTANCE_ID', '$newInstanceId');/g\" $confFile 2>&1";
        exec($command, $output, $returnVar);
        $this->getLogger()->debug("About to execute command:\n$command");
        if ($returnVar) {
            throw new LogicException("Unable to update INSTANCE_ID in conf file\n" . implode("\n", $output));
        }

        $command = "grep -q \"    define('INSTANCE_ID', '$newInstanceId');\" $confFile";
        $this->getLogger()->debug("About to execute command:\n$command");
        exec($command, $output, $returnVar);
        if ($returnVar) {
            throw new LogicException("Unable to locate updated INSTANCE_ID in conf file");
        }

        $this->getLogger()->notice("INSTANCE_ID updated in conf file from '$oldInstanceId' to '$newInstanceId'");
    }


    /**
     * Renames redis keys that contain the instance id
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     */
    private function updateRedisKeys(string $oldInstanceId, string $newInstanceId): void
    {
        // Examples: (INSTANCE_ID = 24d3025b)
        //  msg:24d3025b:m:3f94e52e755c61fc492361e049be2613c8732c24:da1121176f9b3e1264a4e0084a62c05d5de468a1"
        //  schedule:24d3025b:896f0580a8d7c9b8283113e64c8db6b7187766b5
        //  External_Synergetic_Timetable.getTimetableForDate:24d3025b:a2012baed11fce768ff08afc3ac51895f1c99f23493c525bb6b9db107627dd20
        //  External_Synergetic_User.getPortrait:24d3025b:4bb5c1ab01f8388f344cd858f4eec5d9d1512a56498f14298619f10d21c6b8d3
        //  m:24d3025b:routes
        //  scrub.html:24d3025b:f5b376f03db472410fa1494bb0c8a253ee4723208bda85110a3e05958e89051b

        $this->getLogger()->info("Fetching redis keys containing ':$oldInstanceId:' ...");
        $oldKeys = $this->redis->keys("*:$oldInstanceId:*");

        $this->getLogger()->notice(sprintf("Renaming %d redis keys", count($oldKeys)));
        foreach ($oldKeys as $oldKey) {
            $newKey = preg_replace("/:$oldInstanceId:/", ":$newInstanceId:", $oldKey);
            $this->getLogger()->debug("Renaming '$oldKey' to '$newKey'");
            $this->redis->rename($oldKey, $newKey);
        }
    }


    /**
     * Renames redis keys that contain the instance id
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     * @throws PDOException
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    private function updateEtherpadEnvironments(string $oldInstanceId, string $newInstanceId): void
    {
        $padMapper = PadMapper::getInstance();

        $pads = $padMapper->findByColumn('environment', $oldInstanceId, ['deleted' => true]);
        $this->getLogger()->info($pads->count() . " ether pads require update");
        foreach ($pads as $pad) {
            $pad->setEnvironment($newInstanceId);
            $padMapper->save($pad);
            $this->getLogger()->debug("Pad {$pad->getId()} updated");
        }
    }


    /**
     * Renames a bunch of lock files on disk
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     */
    private function renameLockFiles(string $oldInstanceId, string $newInstanceId): void
    {
        $streamDir = rtrim($this->getConfig()->get('stream_dir'), '/') . '/';

        $handle = opendir($streamDir);
        while (false !== ($file = readdir($handle))) {
            if ('.' === $file || '..' === $file){
                continue;
            }
            if(!preg_match("/$oldInstanceId\.lock/", $file)){
                continue;
            }
            $newFile = preg_replace("/$oldInstanceId\.lock/", "$newInstanceId.lock", $file);
            $this->getLogger()->debug("Renaming lock file from '$file' to '$newFile'");
            rename($streamDir . $file, $streamDir . $newFile);
        }
    }


    /**
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     */
    private function renameMessageQueues(string $oldInstanceId, string $newInstanceId): void
    {
        // https://stackoverflow.com/questions/57768315/how-to-rename-queue-in-rabbitmq
        $amqpConn = \Schoolbox\Notif\MQ\Connector::getInstance();
        // TODO

    }


    /**
     * Rebuild the skin (it's output contains the instance id)
     */
    private function rebuildSkin(): void
    {
        $skinBuilder = __DIR__ . '/../../../tools/skin/builder.php';
        $skinBuilder = realpath($skinBuilder);
        $this->getLogger()->info("Invoking skin builder at: '$skinBuilder'");
        passthru("php $skinBuilder -f", $returnCode);
        if ($returnCode) {
            $this->getLogger()->error("Unexpected return code when running skin builder");
        }
    }


    /**
     * Rebuild subscripts so users are bound to correct channels
     * @param string $newInstanceId
     * @throws ServiceNotFound
     * @throws UnderflowException
     */
    private function rebuildSubscriptionCache(string $newInstanceId): void
    {
        /** @var WritableCache $cache */
        $cache = $this->getDi()->get('subscriptionCache');
        $cache->cacheAllUsers($newInstanceId);
    }


    /**
     * Restart daemons so they pick up the new instance id
     */
    private function restartDaemons(): void
    {
        // TODO, this is the only definition of these constants I could find, they should be defined
        // somewhere more appropriate, but still better than using magic strings.
        $daemons = [
            \Alaress\Async\Test\TestCase::DAEMON_BELL,
            \Alaress\Async\Test\TestCase::DAEMON_DIGEST,
            \Alaress\Async\Test\TestCase::DAEMON_ROUTE,
            \Alaress\Async\Test\TestCase::DAEMON_SCHEDULE,
            \Alaress\Async\Test\TestCase::DAEMON_SOCKET,
        ];

        foreach ($daemons as $daemon) {
            $liveOrDev = $this->getConfig()->get('site_type') === 'dev' ? 'dev' : '';
            $command   = "service schoolbox$liveOrDev-$daemon restart";
            $this->getLogger()->debug("Executing:\n    $command");
            exec($command, $output, $returnCode);
            if ($returnCode) {
                $this->getLogger()->error("Unexpected return code when restarting daemon '$daemon':\n" . implode("\n", $output));
            }
        }
    }
}
