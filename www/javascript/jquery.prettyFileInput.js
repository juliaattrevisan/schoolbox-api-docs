/******* 

jQuery prettyFileInput

"if ya needs thar a prettier file inputstick tharn come on doooown"

This changes file inputs to be prettier! 
I started with this basic converter

http://www.viget.com/inspire/custom-file-inputs-with-a-bit-of-jquery/

so thanks mr dudez for that!

and turned it into an eacheriser with options. Now you can mekk pretty things happen probably!

v. 0.1

+ inputHolderClass: this wraps all the appended elements and the file input itself.
+ buttonClass: this is the class for the 'button', the thing underneath the invisible file input. how sneaky!
+ additionalBtnClasses: if you need extra classes on the button whack em in here.
+ fakeFileHolderClass: this is the class for the element that holds the filename like "chimpney.jpg"
+ defaultText: This is the text on the button
+ defaultFileSelectedText: This is the text that the button transforms to.

*******/

(function($){
	$.fn.prettyFileInput = function(options) {
		var defaults = {
			inputHolderClass: 'file-input', 
			buttonClass: 'btn',
			additionalBtnClasses: 'no-marg float-left',
			buttonActiveClass: 'tick',
			fakeFileHolderClass: 'file-holder',
			defaultText: '<span></span>Choose file',
			defaultFileSelectedText: '<span></span>File Selected'
			
		};
		var options = $.extend(defaults, options);

		return this.each(function() {
			
			var obj = $(this);
			
			obj.wrap('<span class="'+options.inputHolderClass+'"></span>');
			obj.after('<span class="'+options.buttonClass+' '+options.additionalBtnClasses+'">'+options.defaultText+'</span>');
			
			obj.bind('change focus click',function() {
				
				$val = obj.val(),
				valArray = $val.split('\\'),
				$button = $('.'+options.buttonClass+''),
				$fakeHolder = $('.'+options.fakeFileHolderClass),
				newVal = valArray[valArray.length-1]
				
				if(newVal !== '') {
					obj
					.siblings('.'+options.buttonClass+'')
					.addClass(options.buttonActiveClass)
					.html(options.defaultFileSelectedText);
				}
				
				if (obj.siblings('.'+options.fakeFileHolderClass).length === 0) {
					obj.after('<span class="'+options.fakeFileHolderClass+'">' + newVal + '</span>');
				} else {
					//obj.siblings($fakeHolder).text(newVal);
				}
				
				/*if ((obj.siblings($fakeHolder).length < 0) && newVal == '') {
					obj.siblings($fakeHolder).remove();
					obj.siblings($button).html(options.defaultText).removeClass().addClass(options.buttonClass+' '+options.additionalBtnClasses);
				}
				('.'+options.buttonClass+'')
				*/

			});
		});
	};
})(jQuery);