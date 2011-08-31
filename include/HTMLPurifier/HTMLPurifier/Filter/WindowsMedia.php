<?php

class HTMLPurifier_Filter_WindowsMedia extends HTMLPurifier_Filter
{
    public $name = 'WindowsMedia';

    public function preFilter($html, $config, $context) {

		/* First find out if the autostart param exists
		 */
		$pattern = '#<object[^>]*?classid="clsid:6bf52a52-394a-11d3-b153-00c04f79faa6".*?width="([^"]+)".*?height="([^"]+)"[^>]*?>'.
					'.*?<param name="autostart" value="([\w]+)"[^>]*?>' .
					'.*?<param name="url" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>' .
					'.*?</object>#s';

		/* Extract an autostart value.
		 *
		 * If $pattern matched, then an autostart value must have been provided
		 * (by default, autostart is true and the tag is nonexistent) and thus
		 * the autostart value is false
		 */
		if (preg_match($pattern, $html)) {
			$pre_regex = $pattern;
			$pre_replace = '<span class="windowsmedia-embed width-\1 height-\2 autostart-false">\4</span>';
		}
		else {
			$pre_regex = '#<object[^>]*?classid="clsid:6bf52a52-394a-11d3-b153-00c04f79faa6".*?width="([^"]+)".*?height="([^"]+)"[^>]*?>'.
						 '.*?<param name="url" value="([A-Za-z0-9\-_\.\?/=&:%]+)"[^>]*?>' .
						 '.*?</object>#s';

			$pre_replace = '<span class="windowsmedia-embed width-\1 height-\2 autostart-true">\3</span>';
		}

		$result = preg_replace($pre_regex, $pre_replace, $html);

		return $result;
    }

    public function postFilter($html, $config, $context) {
        $post_regex = '#<span class="windowsmedia-embed width-([\d]+) height-([\d]+) autostart-([\w]+)">([A-Za-z0-9\-_\.\?/=&:%]+)</span>#';

        $result = preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);

		return $result;
    }

    protected function armorUrl($url) {
        return str_replace('--', '-&#45;', $url);
    }

    protected function postFilterCallback($matches) {
        $url = $this->armorUrl($matches[4]);

		$width = $matches[1];
		$height = $matches[2];
		$autostart = $matches[3];

        return  '<object classid="clsid:6bf52a52-394a-11d3-b153-00c04f79faa6" '.
				'width="'.$width.'" height="'.$height.'">'.
				'<param name="url" value="'.$url.'" />'.
				'<param name="autostart" value="' . $autostart . '" />'.
				'<param name="wmode" value="transparent" />'.
				'<param name="WindowlessVideo" value="-1" />'.
				'<!--[if !IE]-->'.
				'<object type="application/x-ms-wmp" data="'.$url.'" width="'.$width.'" height="'.$height.'" >'.
				'<param name="src" value="'.$url.'" />'.
				'<param name="autostart" value="' . $autostart . '" />'.
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
