<?php

class HTMLPurifier_Filter_Real extends HTMLPurifier_Filter
{
    public $name = 'Real';

    public function preFilter($html, $config, $context) {
		$pre_regex = '#<object[^>]*?width="([\d]+)"[^>]*?height="([\d]+)"[^>]*?(classid="clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa"|type="audio/x-pn-realaudio-plugin")[^>]*?>'.
					 '.*?<param name="src" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>.*?</object>#s';
        $pre_replace = '<span class="real-embed width-\1 height-\2">\3</span>';
       	
		return preg_replace($pre_regex, $pre_replace, $html);
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="real-embed width-([\d]+) height-([\d]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';
        return preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);
    }

    protected function armorUrl($url) {
        return str_replace('--', '-&#45;', $url);
    }

    protected function postFilterCallback($matches) {
        $url = $this->armorUrl($matches[3]);
		$width = $matches[1];
		$height = $matches[2];
        return  '<object '.
				'width="'.$width.'" height="'.$height.'" '.
                'classid="clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa">'.
				'<param name="src" value="'.$url.'" />'.
				'<param name="autostart" value="true" />'.				
				'<!--[if !IE]-->'.
				'<object type="audio/x-pn-realaudio-plugin" data="'.$url.'" width="'.$width.'" height="'.$height.'" >'.
				'<param name="src" value="'.$url.'" />'.
				'<param name="autostart" value="true" />'.
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