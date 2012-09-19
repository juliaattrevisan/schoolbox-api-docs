<?php

class HTMLPurifier_Filter_Quicktime extends HTMLPurifier_Filter
{
    public $name = 'Quicktime';

    public function preFilter($html, $config, $context) {
		$pre_regex = '#<object[^>]*?width="([\d]+)"[^>]*?height="([\d]+)"[^>]*?(classid="clsid:02bf25d5-8c17-4b23-bc80-d3488abddc6b"|type="video/quicktime")[^>]*?>'.
					 '.*?<param name="src" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>.*?</object>#s';
        $pre_replace = '<span class="quicktime-embed width-\1 height-\2">\4</span>';
       	
		//return '<span>'.htmlentities($html).'</span>';
		return preg_replace($pre_regex, $pre_replace, $html);
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="quicktime-embed width-([\d]+) height-([\d]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';
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
                'classid="clsid:02bf25d5-8c17-4b23-bc80-d3488abddc6b">'.
				'<param name="src" value="'.$url.'"/>'.
				'<param value="true" name="autostart" />'.
				'<param value="true" name="saveembedtags" />'.
				'<param value="true" name="postdomevents" />'.
				'<param name="wmode" value="transparent" />'.
                '<![if !IE]>'.
				'<object type="video/quicktime" data="'.$url.'" width="'.$width.'" height="'.$height.'">'.
				'<param value="true" name="autostart" />'.
				'<param value="true" name="saveembedtags" />'.
				'<param value="true" name="postdomevents" />'.
				'<param name="wmode" value="transparent" />'.
				'<![endif]>'.
				'<a href="http://www.apple.com/quicktime/download/">QuickTime required. Free download</a>'.
				'<![if !IE]>'.
				'</object>'.
				'<![endif]>'.
				'</object>';
    }
}

// vim: et sw=4 sts=4

?>