(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationRichText = function(options) {
		var config = init(options);

		initChange();

		/**
		 * Set up options
		 *
		 * @param options
		 * @returns {*}
		 */
		function init(options) {
			var defaults = {
				id: 0
			}

			var derived = {
				editorSelector: 'richtext-' + options.id,
				questionForm: $('#questionForm-' + options.id)
			}

			return $.extend(defaults, derived, options);
		}

		/**
		 * Initialise tiny
		 */
		function initChange() {
			tinyMCE.init({
				mode : "specific_textareas",
				editor_selector: config.editorSelector,
				theme : "advanced",
				skin : "cirkuit",
				plugins : "pdw,table,tabfocus,media,inlinepopups,advimage,assetmanager,directionality,paste,spellchecker",
				content_css : "/css/core/tiny.css",
				theme_advanced_toolbar_location : "top",
				theme_advanced_toolbar_align : "left",
				theme_advanced_statusbar_location : "bottom",
				theme_advanced_buttons1 : "bold,italic,underline,separator,bullist,numlist,separator,link,unlink,image,media,separator,undo,redo,separator,pdw_toggle",
				theme_advanced_buttons2 : "forecolor,formatselect,fontsizeselect,separator,justifyleft,justifycenter,justifyright,spellchecker",
				theme_advanced_buttons3 : "outdent,indent,sub,sup,separator,hr,charmap,separator,seperator,ltr,rtl,selectall,pastetext,pasteword,separator,removeformat,cleanup,code",
				theme_advanced_buttons4 : "tablecontrols",
				accessibility_warnings : false,
				pdw_toggle_on : 1,
				pdw_toggle_toolbars : '2, 3, 4',
				extended_valid_elements : "a[class|name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]",
				file_browser_callback : 'tinyMCE.activeEditor.plugins.assetmanager.fileBrowser',
				file_uploader_callback : 'tinyMCE.activeEditor.plugins.assetmanager.fileUploader',
				file_browser_category_id : 0,
				tabfocus_elements : ":prev,:next",
				convert_urls : false,
				force_br_newlines : false,
				force_p_newlines : true,
				setup : function(ed) {
					ed.onInit.add(function(ed) {
						var dom = ed.dom,
							doc = ed.getDoc(),
							el = doc.content_editable ? ed.getBody() : (tinymce.isGecko ? doc : ed.getWin());

						tinymce.dom.Event.add(el, 'blur', function(e) {
							$.fn.evaluationRichText.saveOne(config.questionForm);
						});
					});
				}
			});
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationRichText.saveOne = function(element) {
		tinyMCE.triggerSave();

		$.ajax({
			type: 'post',
			url: $(element).attr('action'),
			data: $(element).serialize(),

			/* Ensure all forms submit before redirecting
			 * Do not upgrade jquery to 1.9 or you'll lose this
			 */
			async: false,

			/* Callback on success
			 */
			success: function(responseText) {
				var result = new Function('return ' + responseText);

				$.fn.evaluationPercentageComplete({
					result: result()
				});
			}
		});
	}

	/**
	 * Decide what the validation rules are for this particular
	 * question type. Optionally specify errorContainer if you want
	 * the validation messages to appear in a special location,
	 * otherwise leave it undefined for default behaviour
	 *
	 * @returns {{element: string, rules: {required: boolean, minlength: number}, errorContainer: string}}
	 */
	$.fn.evaluationMultipleChoice.getValidationRules = function() {
	}
}(jQuery));