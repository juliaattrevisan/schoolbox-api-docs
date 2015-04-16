<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Collection;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('New Permission Style', [
    'verbose' => [ 'v', 'v', 'v' ]
]);

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    /** @var Collection|RoutePermission[] $perms */
    $perms = RoutePermissionMapper::getInstance()->findAll();

    $map = [
        0   => [ 'MU' ],
        200 => [ 'DI' ],
        250 => [ 'IN' ],
        300 => [ 'IN', 'MO', 'EM' ]
    ];

    $stmt = Database::getInstance()->prepare("
        UPDATE msg_route_perm
        SET MASK = :mask, DEF = :def
        WHERE msg_source_id = :source
        AND role_id = :role
    ");

    foreach ($perms as $perm) {
        $min = $perm->getMinLevel();
        $def = $perm->getDefaultLevel();
        $max = $perm->getMaxLevel();

        $mrg = [];

        for ($i = $min; $i <= $max; $i += 50) {
            if (!isset($map[$i])) {
                continue;
            }
            $mrg = array_merge($mrg, $map[$i]);
        }

        $mrg = array_unique($mrg);

        $def = $map[$def];

        $stmt->execute([
            'mask'   => implode(',', $mrg),
            'def'    => implode(',', $def),
            'source' => $perm->getMsgSource()->getId(),
            'role'   => $perm->getRole()->getId()
        ]);

        $log->info(sprintf("[src:%02d, r:%02d]  %14s (%s)",
            $perm->getMsgSource()->getId(),
            $perm->getRole()->getId(),
            implode(' ', $mrg), implode(' ', $def))
        );
    }



});
