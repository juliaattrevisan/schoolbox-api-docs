<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\EportfolioImage;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Blog\EportfolioImage\Model as EportfolioBlogImageModel;

class Mapper extends BaseMapper
{
   /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_blog_image');
        $this->hasPrototype(new EportfolioBlogImageModel);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('blog_id', self::TYPE_INTEGER);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }
}
