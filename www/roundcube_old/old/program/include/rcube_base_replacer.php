<?php
/**
 * Helper class to turn relative urls into absolute ones
 * using a predefined base
 */
class rcube_base_replacer
{
  private $base_url;

  public function __construct($base)
  {
    $this->base_url = $base;
  }

  public function callback($matches)
  {
    return $matches[1] . '="' . make_absolute_url($matches[3], $this->base_url) . '"';
  }
}

?>
