<?php
/**
 * created by jetbrains phpstorm.
 * user: jleckie
 * date: 3/21/12
 * time: 9:56 pm
 */

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Schoolbox\WebDav\FileServer\Folder as WebDavFolder;
use Schoolbox\WebDav\NameGenerator as WebDavNameGenerator;
use Schoolbox\WebDav\SubjectServer\Server as WebDavSubjectList;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var WebDavFolder $publicdir */
/** @var MapperFactory $mapperFactory */
$mapperFactory = $kernel->get('mapperFactory');
$userSource    = $kernel->getDependencyInjector()->lazyGet('user');
$publicdir     = (new WebDavFolder(
    '',
    $userSource,
    $mapperFactory->get(\Schoolbox\Resource\Folder\Mapper::class),
    $mapperFactory->get(\Schoolbox\Resource\File\Mapper::class),
    $mapperFactory->get(\Schoolbox\Resource\Storage\Mapper::class),
    $mapperFactory->get(\Schoolbox\Permission\Access\Access::class)
))->addSubjectList(
    new WebDavSubjectList(
        'My Subjects',
        $userSource,
        $kernel->get('assessmentDataSource'),
        $mapperFactory->get(\Schoolbox\Resource\Storage\Mapper::class),
        $kernel->get('submissionDataSource'),
        $mapperFactory->get(ErrorMapper::class),
        new WebDavNameGenerator()
    )
);

$server = new Sabre\DAV\Server($publicdir);
$plugin = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($plugin);

$lockBackend = new Sabre\DAV\Locks\Backend\File(sys_get_temp_dir() . '/webdavlocks.dat');
$lockPlugin  = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

/** @var IConfigLoader $config */
$config             = $kernel->get('config');
$authBackendFactory = new DAV_Auth_Factory($config);
$authPlugin         = new Sabre\DAV\Auth\Plugin($authBackendFactory->build());
$server->addPlugin($authPlugin);
$server->setBaseUri('/');
$server->exec();
