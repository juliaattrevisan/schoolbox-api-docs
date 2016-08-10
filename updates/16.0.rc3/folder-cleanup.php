<?php

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;

$folderName = "Lost & Found";

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("Orphaned Folder Cleanup");

$db      = Database::getInstance();
$logger  = new Alaress\Lumberjack\Logger(STDOUT, null, Monolog\Logger::INFO, [], isset($options['porcelain']));

$folderName = $db->quote($folderName);

$lostQuery = $db->prepare("SELECT `id` FROM `category` WHERE `name` = {$folderName}");
$lostQuery->execute();

if ($lostQuery->rowCount() > 0) {
    $row = $lostQuery->fetch();
    $parent_id = $row['id'];
}
else
{
    $db->exec("INSERT INTO `category` (name, parent, inherit) VALUES ({$folderName}, 0, 0)");
    $parent_id = $db->lastInsertId();
}

$moveQuery = $db->prepare("UPDATE `category` SET `parent` = :parent_id WHERE `id` = :id;");

$findFolderQuery = $db->prepare("SELECT id, name, parent FROM `category` WHERE id = :id");

$findAllFoldersQuery = $db->prepare("SELECT id, name, parent FROM `category` WHERE `deleted_at` IS NULL");

// Do a recursive lookup on every item and make sure the ancestry is valid

do {

    $errors = 0;

    $findAllFoldersQuery->execute();
    $folders = $findAllFoldersQuery->fetchAll();

    /** @var array $folder */
    foreach ($folders as $folder)
    {
        // Keep track of nodes we have traversed so we don't end up in a loop

        $folderCurrent = $folder;
        $found = [];

        try
        {
            while ($folderCurrent['parent'] != 0)
            {
                $found[$folderCurrent['id']] = 1;

                /** @var array $folderCurrent */
                $findFolderQuery->execute(['id' => $folderCurrent['parent']]);
                if ($findFolderQuery->rowCount() == 0) {
                    throw new \Exception('No folder found');
                }
                $folderCurrent = $findFolderQuery->fetch();

                if ($folderCurrent['parent'] == null)
                {
                    // Node has no parent set

                    $logger->info(sprintf("Orphaned Folder '%s' (%d) moved to {$folderName} (%d)", $folder['name'],
                        $folder['id'], $parent_id));

                    $moveQuery->execute([
                        'parent_id' => $parent_id,
                        'id' => $folder['id']
                    ]);

                    $errors++;

                    break;
                }
                else if (isset($found[$folderCurrent['id']]))
                {
                    // Found a looping family tree

                    $loopString = "";

                    foreach ($found as $id => $item)
                        $loopString .= "{$id},";

                    $loopString = $loopString . $folderCurrent['id'];

                    $logger->info(sprintf("Looping Folder '%s' (%s) moved to {$folderName} (%d)", $folder['name'],
                        $loopString, $parent_id));
                    $moveQuery->execute([
                        'parent_id' => $parent_id,
                        'id' => $folder['id']
                    ]);

                    $errors++;

                    break;
                }
            }
        }
        catch (\Exception $e)
        {
            $logger->info(sprintf("Folder '%s' (%d) missing Parent (%d) moved to {$folderName} (%d)", $folder['name'],
                $folder['id'], $folder['parent'], $parent_id));

            $moveQuery->execute([
                'parent_id' => $parent_id,
                'id' => $folder['id']
            ]);

            $errors++;
        }
    }

} while ($errors > 0);
