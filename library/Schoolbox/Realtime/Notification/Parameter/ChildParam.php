<?php

 /**
  * @author    Amri Sannang <amri.sannang@alaress.com.au>
  * @copyright Copyright 2019, Schoolbox Pty Ltd
  * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Notification\Parameter;

/**
 * UserParam variant.
 *
 * This parameter should be used in a notification that is sent to a parent,
 * and its subject should be one of the parent's children.
 *
 * (We assume that parents will usually know their children by their first name.)
 */
class ChildParam extends UserParam
{
    /**
     * @inheritdoc
     */
    public function __construct($user, $stacking = self::STACKING_APPEND, $format = '{preferredname}')
    {
        parent::__construct($user, $stacking, $format);
    }
}
