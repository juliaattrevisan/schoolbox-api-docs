<?php

class HTMLPurifier_Filter_Shockwave extends HTMLPurifier_Filter
{
    public $name = 'Shockwave';

    public function preFilter($html, $config, $context) {
		$pre_regex = '#<object[^>]*?classid="clsid:166b1bca-3f9c-11cf-8075-444553540000"[^>]*?width="([^"]+)"[^>]*?height="([^"]+)"[^>]*?>'.
					 '.*?<param name="src" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>.*?</object>#s';
        $pre_replace = '<span class="shockwave-embed width-\1 height-\2">\3</span>';
       
		//return '<span>'.htmlentities($html).'</span>';
		return preg_replace($pre_regex, $pre_replace, $html);
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="shockwave-embed width-([\d]+) height-([\d]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';
        return preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);
    }

    protected function armorUrl($url) {
        return str_replace('--', '-&#45;', $url);
    }

    protected function postFilterCallback($matches) {
        $url = $this->armorUrl($matches[3]);
		$width = $matches[1];
		$height = $matches[2];
        return  '<object classid="clsid:166b1bca-3f9c-11cf-8075-444553540000" '.
				'width="'.$width.'" height="'.$height.'">'.
				'<param name="src" value="'.$url.'"/>'.
				'<param name="sound" value="true" />'.
				'<param name="progress" value="true" />'.
				'<param name="autostart" value="true" />'.
				'<param name="wmode" value="transparent" />'.
				'<!--[if !IE]-->'.
				'<object type="application/x-director" data="'.$url.'" width="'.$width.'" height="'.$height.'">'.
				'<param name="sound" value="true" />'.
				'<param name="progress" value="true" />'.
				'<param name="autostart" value="true" />'.
				'<param name="wmode" value="transparent" />'.
				'<!--<![endif]-->'.
				'<a href="http://www.adobe.com/go/getshockwaveplayer"><img src="http://www.adobe.com/images/shared/download_buttons/get_adobe_shockwave_player.png" alt="Get Adobe Shockwave player" /></a>'.
				'<!--[if !IE]-->'.
				'</object>'.
				'<!--<![endif]-->'.
				'</object>';
    }
}

// vim: et sw=4 sts=4

?>