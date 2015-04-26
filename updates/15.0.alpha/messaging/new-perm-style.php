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
use Schoolbox\Realtime\Message\Reroute;
use Schoolbox\Realtime\Message\RerouteMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
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
        SET mask = :mask, role_default = :def
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

        if (is_null($def)) {
            continue;
        }

        $stmt->execute([
            'mask'   => implode(',', $mrg),
            'def'    => implode(',', $def),
            'source' => $perm->getMsgSource()->getId(),
            'role'   => $perm->getRole()->getId()
        ]);

        $log->info(sprintf("[src:%03d, r:%03d]  %14s (%s)",
            $perm->getMsgSource()->getId(),
            $perm->getRole()->getId(),
            implode(' ', $mrg), implode(' ', $def))
        );
    }



    /** @var Collection|Reroute[] $reroutes */
    $reroutes = RerouteMapper::getInstance()->findAll();

    $stmt = Database::getInstance()->prepare("
        UPDATE msg_reroute
        SET delivery = :delivery
        WHERE msg_source_id = :source
        AND user_id = :user
    ");

    foreach ($reroutes as $reroute) {
        $outgoing = $reroute->getOutgoingLevel();
        $delivery = isset($map[$outgoing]) ? $map[$outgoing] : $map[0];

        if (is_null($delivery)) {
            continue;
        }

        $stmt->execute([
            'delivery' => implode(',', $delivery),
            'source'   => $reroute->getSource()->getId(),
            'user'     => $reroute->getUser()->getId()
        ]);

        $log->info(sprintf("[src:%03d, u:%03d]  %14s",
            $reroute->getSource()->getId(),
            $reroute->getUser()->getId(),
            implode(' ', $delivery))
        );

    }


    /** @var Collection|RerouteOverride[] $overrides */
    $overrides = RerouteOverrideMapper::getInstance()->findAll();

    $stmt = Database::getInstance()->prepare("
        UPDATE msg_reroute_override
        SET delivery = :delivery
        WHERE user_id = :user
        AND msg_route_key = :route
    ");

    foreach ($overrides as $override) {
        $outgoing = $override->getOutgoingLevel();
        $delivery = isset($map[$outgoing]) ? $map[$outgoing] : $map[0];

        if (is_null($delivery)) {
            continue;
        }

        $stmt->execute([
            'delivery' => implode(',', $delivery),
            'user'     => $override->getUser()->getId(),
            'route'    => $override->getRouteKey()
        ]);

        $log->info(sprintf("[key:%-32s, u:%03d]  %14s",
                $override->getRouteKey(),
                $override->getUser()->getId(),
                implode(' ', $delivery))
        );

    }

});
