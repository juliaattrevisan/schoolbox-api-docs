<?php
 
class Alaress_Text_Diff_Renderer_Inline extends Horde_Text_Diff_Renderer_Inline {
	protected function _changed($orig, $final) {
		/* If we've already split on characters, just display. */
		if ($this->_split_level == 'characters') {
			return $this->_deleted($orig)
				. $this->_added($final);
		}

		/* If we've already split on words, just display. */
		if ($this->_split_level == 'words') {
			$prefix = '';
			while ($orig[0] !== false && $final[0] !== false &&
				   substr($orig[0], 0, 1) == ' ' &&
				   substr($final[0], 0, 1) == ' ') {
				$prefix .= substr($orig[0], 0, 1);
				$orig[0] = substr($orig[0], 1);
				$final[0] = substr($final[0], 1);
			}
			return $prefix . $this->_deleted($orig) . $this->_added($final);
		}

		$text1 = implode("\n", $orig);
		$text2 = implode("\n", $final);

		/* Non-printing newline marker. */
		$nl = "\0";

		if ($this->_split_characters) {
			$diff = new Horde_Text_Diff('native',
								  array(preg_split('//', $text1),
										preg_split('//', $text2)));
		} else {
			/* We want to split on word boundaries, but we need to preserve
			 * whitespace as well. Therefore we split on words, but include
			 * all blocks of whitespace in the wordlist. */
			$diff = new Horde_Text_Diff('native',
								  array($this->_splitOnWords($text1, $nl),
										$this->_splitOnWords($text2, $nl)));
		}

		/* Get the diff in inline format. */
		$renderer = new Alaress_Text_Diff_Renderer_Inline
			(array_merge($this->getParams(),
						 array('split_level' => $this->_split_characters ? 'characters' : 'words')));

		/* Run the diff and get the output. */
		return str_replace($nl, "\n", $renderer->render($diff)) . "\n";
	}

	protected function _encode (&$string) {
		
	}
}
