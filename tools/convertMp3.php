<?php

/* This script will scan Schoolbox FS for audio files and convery them to MP3.  This will make sure they play in
 * the audio player as of version 14.
 */

require_once(dirname(__FILE__) . '/../library/Bootstrap.php');


$dataDir = \Config::get('file_dir');

$query = "SELECT * FROM file WHERE mime_type like 'audio/%' AND mime_type != 'audio/mpeg'";
$result = \Database::getInstance()->query($query);

foreach($result as $row) {
    exec( "avconv -i {$dataDir}/{$row['id']}.dat -ar 44100 -ac 2 -ab 128 -f mp3 {$dataDir}/{$row['id']}.mp3", $info_message, $info_return);

    if (($info_return == 0)) {
        $query = "UPDATE file SET mime_type = 'audio/mpeg', realname = CONCAT(realname,'.mp3') WHERE id = :id";
        $update = \Database::getInstance()->prepare($query);
        $update->execute(array(':id'=>$row['id']));

        rename("{$dataDir}/{$row['id']}.dat", "{$dataDir}/{$row['id']}.old" );
        rename("{$dataDir}/{$row['id']}.mp3", "{$dataDir}/{$row['id']}.dat" );
    }
}
