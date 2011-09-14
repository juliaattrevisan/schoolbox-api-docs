<?php
$this->setBlock('config', 'language', 'language_block');
$this->setBlock($componentHandle, 'translate_into', 'translate_into_block');

$allLanguages = array(
	array('code' => "af", 'name' => "Afrikaans"),
	array('code' => "sq", 'name' => "Albanian"),
	array('code' => "ar", 'name' => "Arabic"),
	array('code' => "hy", 'name' => "Armenian ALPHA"),
	array('code' => "az", 'name' => "Azerbaijani ALPHA"),
	array('code' => "eu", 'name' => "Basque ALPHA"),
	array('code' => "be", 'name' => "Belarusian"),
	array('code' => "bg", 'name' => "Bulgarian"),
	array('code' => "ca", 'name' => "Catalan"),
	array('code' => "zh-CN", 'name' => "Chinese (Simplified)"),
	array('code' => "zh-TW", 'name' => "Chinese (Traditional)"),
	array('code' => "hr", 'name' => "Croatian"),
	array('code' => "cs", 'name' => "Czech"),
	array('code' => "da", 'name' => "Danish"),
	array('code' => "nl", 'name' => "Dutch"),
	array('code' => "en", 'name' => "English"),
	array('code' => "et", 'name' => "Estonian"),
	array('code' => "tl", 'name' => "Filipino"),
	array('code' => "fi", 'name' => "Finnish"),
	array('code' => "fr", 'name' => "French"),
	array('code' => "gl", 'name' => "Galician"),
	array('code' => "ka", 'name' => "Georgian ALPHA"),
	array('code' => "de", 'name' => "German"),
	array('code' => "el", 'name' => "Greek"),
	array('code' => "ht", 'name' => "Haitian Creole ALPHA"),
	array('code' => "iw", 'name' => "Hebrew"),
	array('code' => "hi", 'name' => "Hindi"),
	array('code' => "hu", 'name' => "Hungarian"),
	array('code' => "is", 'name' => "Icelandic"),
	array('code' => "id", 'name' => "Indonesian"),
	array('code' => "ga", 'name' => "Irish"),
	array('code' => "it", 'name' => "Italian"),
	array('code' => "ja", 'name' => "Japanese"),
	array('code' => "ko", 'name' => "Korean"),
	array('code' => "lv", 'name' => "Latvian"),
	array('code' => "lt", 'name' => "Lithuanian"),
	array('code' => "mk", 'name' => "Macedonian"),
	array('code' => "ms", 'name' => "Malay"),
	array('code' => "mt", 'name' => "Maltese"),
	array('code' => "no", 'name' => "Norwegian"),
	array('code' => "fa", 'name' => "Persian"),
	array('code' => "pl", 'name' => "Polish"),
	array('code' => "pt", 'name' => "Portuguese"),
	array('code' => "ro", 'name' => "Romanian"),
	array('code' => "ru", 'name' => "Russian"),
	array('code' => "sr", 'name' => "Serbian"),
	array('code' => "sk", 'name' => "Slovak"),
	array('code' => "sl", 'name' => "Slovenian"),
	array('code' => "es", 'name' => "Spanish"),
	array('code' => "sw", 'name' => "Swahili"),
	array('code' => "sv", 'name' => "Swedish"),
	array('code' => "th", 'name' => "Thai"),
	array('code' => "tr", 'name' => "Turkish"),
	array('code' => "uk", 'name' => "Ukrainian"),
	array('code' => "ur", 'name' => "Urdu ALPHA"),
	array('code' => "vi", 'name' => "Vietnamese"),
	array('code' => "cy", 'name' => "Welsh"),
	array('code' => "yi", 'name' => "Yiddish"),
);

foreach($allLanguages as $currentLanguage) {
	$this->setVar(array(
		'COMPONENT_LANGUAGE_CODE' => $currentLanguage['code'],
		'COMPONENT_LANGUAGE_NAME' => $currentLanguage['name'],
		'COMPONENT_LANGUAGE_SELECTED' => $currentLanguage['code'] == $config['language'] ? ' selected="selected"' : '',
	));

	$this->parse('language_block', 'language', true);
	$this->parse('translate_into_block', 'translate_into', true);
}

