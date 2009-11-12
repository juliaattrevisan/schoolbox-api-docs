<?php

class HTMLPurifier_Filter_WindowsMedia extends HTMLPurifier_Filter
{
    public $name = 'WindowsMedia';

    public function preFilter($html, $config, $context) {
		$pre_regex = '#<object[^>]*?classid="clsid:6bf52a52-394a-11d3-b153-00c04f79faa6".*?width="([^"]+)".*?height="([^"]+)"[^>]*?>'.
					 '.*?<param name="url" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>.*?</object>#s';
        $pre_replace = '<span class="windowsmedia-embed width-\1 height-\2">\3</span>';
       	
		//return '<span>'.htmlentities($html).'</span>';
		return preg_replace($pre_regex, $pre_replace, $html);
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="windowsmedia-embed width-([\d]+) height-([\d]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';
        return preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);
    }

    protected function armorUrl($url) {
        return str_replace('--', '-&#45;', $url);
    }

    protected function postFilterCallback($matches) {
        $url = $this->armorUrl($matches[3]);
		$width = $matches[1];
		$height = $matches[2];
        return  '<object classid="clsid:6bf52a52-394a-11d3-b153-00c04f79faa6" '.
				'width="'.$width.'" height="'.$height.'">'.
				'<param name="url" value="'.$url.'" />'.
				'<param name="autostart" value="true" />'.
				'<param name="wmode" value="transparent" />'.
				'<param name="WindowlessVideo" value="-1" />'.
				'<!--[if !IE]-->'.
				'<object type="application/x-ms-wmp" data="'.$url.'" width="'.$width.'" height="'.$height.'" >'.
				'<param name="src" value="'.$url.'" />'.
				'<param name="autostart" value="true" />'.
				'<param name="wmode" value="transparent" />'.
				'<!--<![endif]-->'.
				'<a href="http://www.microsoft.com/windows/windowsmedia/player/download/">Download Windows Media Player.</a>'.
				'<!--[if !IE]-->'.
				'</object>'.
				'<!--<![endif]-->'.
				'</object>';
    }
}

// vim: et sw=4 sts=4

?>