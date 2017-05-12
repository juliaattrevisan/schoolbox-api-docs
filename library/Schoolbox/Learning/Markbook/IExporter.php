<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


namespace Schoolbox\Learning\Markbook;

/**
 * Each implementation of this interface is able to export a markbook object to some format
 */
interface IExporter
{
    /**
     * @param Markbook $markbook
     * @param string $filename
     * @return void
     */
    public function export(Markbook $markbook, $filename);
}
