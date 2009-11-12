<?php

class HTMLPurifier_Filter_Flash extends HTMLPurifier_Filter
{
    public $name = 'Flash';

    public function preFilter($html, $config, $context) {
		$pre_regex = '#<object[^>]*?width="([\d]+)"[^>]*?height="([\d]+)"[^>]*?(classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"|type="application/x-shockwave-flash")'.
					 '[^>]*?>.*?<param name="src" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>.*?</object>#s';
					 
        $pre_replace = '<span class="flash-embed width-\1 height-\2">\4</span>';
		return preg_replace($pre_regex, $pre_replace, $html);
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="flash-embed width-([\d]+) height-([\d]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';
        return preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);
    }

    protected function armorUrl($url) {
        return str_replace('--', '-&#45;', $url);
    }

    protected function postFilterCallback($matches) {
        $url = $this->armorUrl($matches[3]);
		$width = $matches[1];
		$height = $matches[2];
        return '<object width="'.$width.'" height="'.$height.'" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"  '.
			'data="'.$url.'">'.
            '<param name="movie" value="'.$url.'"></param>'.
			'<param name="src" value="'.$url.'"></param>'.
			'<param name="wmode" value="transparent" />'.
			'<!--[if !IE]-->'.
			'<object type="application/x-shockwave-flash" data="'.$url.'" width="'.$width.'" height="'.$height.'">'.
			'<param name="wmode" value="transparent" />'.
			'<!--<![endif]-->'.
			'<a href="http://www.adobe.com/go/getflashplayer"><img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" /></a>'.
			'<!--[if !IE]-->'.
			'</object>'.
			'<!--<![endif]-->'.
			'</object>';
    }
}

// vim: et sw=4 sts=4

?>