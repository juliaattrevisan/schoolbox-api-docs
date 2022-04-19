<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Config;

use Etherpad\PadMapper;
use LogicException;
use PDOException;
use Predis;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\MQ;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use UnderflowException;
use UnexpectedValueException;

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
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws MapperException
     * @throws UnderflowException
     */
    public function run(): void
    {
        $this->redis = $this->getDi()->get('redis.cache');

        $newInstanceId = $this->getValidNewInstanceIdOrExit();
        $oldInstanceId = defined('INSTANCE_ID') ? INSTANCE_ID : '';
        if ($oldInstanceId === $newInstanceId) {
            $this->getLogger()->notice("New instance id == Old instance id ('{$newInstanceId}'), nothing to do.");
        }

        $this->updateIniFile($oldInstanceId, $newInstanceId);
        $this->updateRedisKeys($oldInstanceId, $newInstanceId);
        $this->updateEtherpadEnvironments($oldInstanceId, $newInstanceId);
        $this->renameLockFiles($oldInstanceId, $newInstanceId);
        $this->renameMessageQueues($oldInstanceId, $newInstanceId);
        $this->rebuildSubscriptionCache($newInstanceId);
        $this->restartDaemons();

        $this->getLogger()->notice('INSTANCE_ID update successful');
    }

    /**
     * Returns a new valid instance id or exits
     *
     * @return string
     */
    private function getValidNewInstanceIdOrExit(): string
    {
        $newInstanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID_NEW);
        if (($newInstanceId ?? '') === '') {
            $this->getLogger()->error(sprintf("You must specify a new instance id using the --%s flag", self::OPT_LONG_INSTANCE_ID_NEW));
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        if (!preg_match('/^[0-9a-z]{8,16}$/', $newInstanceId ?? '')) {
            $this->getLogger()->error("Invalid new instance id '$newInstanceId'. Instance id must be 8-16 char alphanumeric");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        $this->getLogger()->notice("New INSTANCE_ID = '$newInstanceId'");

        return $newInstanceId;
    }


    /**
     * Updates the instance ID in the conf file
     *
     * @param string $oldInstanceId
     * @param string $newInstanceId
     * @throws LogicException
     */
    private function updateIniFile(string $oldInstanceId, string $newInstanceId): void
    {
        $this->getLogger()->notice('Updating conf file');
        $siteRootParts = explode('/', SITE_root);
        $package = end($siteRootParts);
        if ($package === 'vagrant') {
            $package = 'schoolbox';
        }
        $iniFile = "/etc/$package/instances.ini";

        $command = "sed -i -E \"s/\[$oldInstanceId\]/[$newInstanceId]/g\" $iniFile 2>&1";
        exec($command, $output, $returnVar);
        $this->getLogger()->debug("About to execute command:\n$command");
        if ($returnVar) {
            throw new LogicException("Unable to update INSTANCE_ID in ini file\n" . implode("\n", $output));
        }

        $command = "grep -q \"[$newInstanceId]\" $iniFile";
        $this->getLogger()->debug("About to execute command:\n$command");
        exec($command, $output, $returnVar);
        if ($returnVar) {
            throw new LogicException("Unable to locate updated INSTANCE_ID in ini file");
        }

        $this->getLogger()->notice("INSTANCE_ID updated in ini file from '$oldInstanceId' to '$newInstanceId'");
    }


    /**
     * Renames redis keys that contain the instance id
     *
     * However we wont update redis keys that are referenced by stomp, as I don't know how to update stomp messages??
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
        $this->getLogger()->notice('Updating redis keys');
        $this->getLogger()->info("Fetching redis keys containing ':$oldInstanceId:' ...");
        $oldKeys = new Keyspace($this->redis, "*");
        foreach ($oldKeys as $oldKey) {
            // I don't know how to modify payloads in rabbitmq. Seems like we don't need to, so long as it
            // can find the payload in redis, all is well.
            if (preg_match("/^msg:$oldInstanceId:s:.+$/", $oldKey)) {
                $this->getLogger()->notice("Skipping stomp redis payload: $oldKey");
                continue;
            }

            $newKey = preg_replace("/:$oldInstanceId:/", ":$newInstanceId:", $oldKey);
            $this->getLogger()->debug("Renaming '$oldKey' to '$newKey'");

            // If a set, we may need to rename the key and the members
            if ($this->redis->type($oldKey)->getPayload() === 'set') {
                // Special case, create a new set with modified members
                $members = $this->redis->smembers($oldKey);
                foreach ($members as $idx => $member) {
                    $members[$idx] = preg_replace("/:$oldInstanceId:/", ":$newInstanceId:", $member);
                }
                $this->redis->del([$oldKey]);
                $this->redis->sadd($newKey, $members);
            } else {
                $this->redis->rename($oldKey, $newKey);
            }
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
        $this->getLogger()->notice('Updating etherpad environment');
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
        $this->getLogger()->notice('Renaming lock files');
        $streamDir = rtrim($this->getConfig()->get('stream_dir'), '/') . '/';
        $handle = opendir($streamDir);
        while (false !== ($file = readdir($handle))) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            if (!preg_match("/$oldInstanceId\.lock/", $file)) {
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
        $this->getLogger()->notice('Renaming message queues');
        // https://stackoverflow.com/questions/57768315/how-to-rename-queue-in-rabbitmq
        $amqpConn = MQ\Connector::getInstance();

        $queues = [
            "%s.route",
            "%s.sb.reviewsched",
            "%s.sb.sched",
        ];
        foreach ($queues as $queue) {
            $oldQueue = sprintf($queue, $oldInstanceId);
            $newQueue = sprintf($queue, $newInstanceId);
            $this->getLogger()->debug("TODO - rename MQ '$oldQueue' to '$newQueue'");
            // TODO - For now, just manually run `rabbitmqadmin list queues` and check no messages exist.
        }
    }


    /**
     * Rebuild subscripts so users are bound to correct channels
     * @param string $newInstanceId
     * @throws UnderflowException
     */
    private function rebuildSubscriptionCache(string $newInstanceId): void
    {
        $this->getLogger()->notice('Rebuilding subscription cache');
        /** @var WriteCache $cache */
        $cache = $this->getDi()->get('subscriptionCache');
        $cache->setLog($this->getLogger());
        $cache->cacheAllUsers($newInstanceId);
    }


    /**
     * Restart daemons so they pick up the new instance id
     */
    private function restartDaemons(): void
    {
        $this->getLogger()->notice('Rebuilding + restarting daemons');
        exec("schoolbox-rebuild-daemons --conf", $output, $returnCode);
        if ($returnCode) {
            $this->getLogger()->error("Unexpected return code when rebuilding daemons:\n" . implode("\n", $output));
        }
    }
}
