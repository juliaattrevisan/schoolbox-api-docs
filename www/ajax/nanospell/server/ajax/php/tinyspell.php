<?php
// Nanospell, rewritten to use PHP pspell for its backend.
$rawPostData = file_get_contents('php://input');
$post        = json_decode($rawPostData);

$broker = enchant_broker_init();

// Dictionaries, in order of preference
// TODO figure out how dictionaries should be installed
$dicts = [
    enchant_broker_request_dict($broker, Config::get('spelling_checking_language')),
];

if (!$post->id) {
    die("JSON Data sent in unexpected format for Nanospell");
}
if ($post->method == 'spellcheck') {
    $structure = [
        'id'     => $post->id,
        'result' => []
    ];
    foreach ($post->params->words as $word) {
        $correctInAnyLanguage = false;
        $corrections          = [];

        // Check each dictionary
        foreach ($dicts as $dict) {
            $suggestions = [];
            if (!enchant_dict_quick_check($dict, $word, $suggestions)) {
                // Are there any suggestions for this word? Use only this dictionary
                if (!empty($suggestions)) {
                    $corrections = array_merge($corrections, $suggestions);
                }
            } else {
                $correctInAnyLanguage = true;
                break;
            }
        }

        // If this word is correct in any language, then don't show corrections for other langs.
        if (!$correctInAnyLanguage) {
            $structure['result'][$word] = $corrections;
        }

    }
    echo json_encode($structure);
}
if ($post->method == 'listdicts') {
    echo json_encode([
        'id'    => $post->id,
        'dicts' => ['en'],
    ]);
}
