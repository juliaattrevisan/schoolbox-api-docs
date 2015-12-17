<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Hydrator\Custom;


use Psr\Log\LoggerInterface;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use Schoolbox\User as InternalUser;

abstract class AbstractLoggableHydrator implements HydratorInterface
{

    /** @var LoggerInterface */
    private $logger;


    /**
     * @param LoggerInterface $logger
     * @return static
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
        return $this;
    }


    /**
     * @param int    $level
     * @param string $message
     * @param ExternalUser $externalUser
     * @param InternalUser $internalUser
     * @param array  $context
     */
    protected function log($level, $message, $externalUser, $internalUser, $context = [])
    {
        // the logger might not actually be set
        if (!$this->logger) {
            return;
        }

        $core = [ 'externalUserId' => $externalUser->getId() ];
        if ($internalUser->getId()) {
            $core['internalUserId'] = $internalUser->getId();
        }

        // fire and forget!
        $this->logger->log($level, $message, array_merge($core, $context));
    }

}
