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
+ additionalButtonClasses: if you need extra classes on the button whack em in here.
+ fakeFileHolderClass: this is the class for the element that holds the filename like "chimpney.jpg"
+ defaultText: This is the text on the button
+ defaultFileSelectedText: This is the text that the button transforms to.

*******/

(function($){
	$.fn.prettyFileInput = function(options) {
		var defaults = {
			inputHolderClass: 'file-input', 
			buttonClass: 'btn',
			additionalButtonClasses: 'btn-file-input',
			buttonActiveClass: 'btn-file-input-active',
			fakeFileHolderClass: 'file-holder',
			defaultText: 'Choose file',
			defaultFileSelectedText: 'File selected'
			
		};
		var options = $.extend(defaults, options);

		return this.each(function() {
			
			var obj = $(this);
			
			obj.wrap('<span class="'+options.inputHolderClass+'"></span>');
			obj.after('<span class="'+options.buttonClass+' '+options.additionalButtonClasses+'">'+options.defaultText+'</span>');
			
			obj.bind('change focus click',function() {
				
				$val = obj.val();
				
				valArray = $val.split('\\');
     			newVal = valArray[valArray.length-1];
				$button = $('.'+options.buttonClass+'');
				$fakeHolder = $('.'+options.fakeFileHolderClass+'');
				
				//if the new value isn't empty, add an active class to the button and change the text of the button
				if(newVal !== '') {
					obj
					.siblings('.'+options.buttonClass+'')
					.addClass(options.buttonActiveClass)
					.html(options.defaultFileSelectedText);
				}
				
				if (obj.siblings('.'+options.fakeFileHolderClass+'').length === 0) {
					obj.parent().append('<span class="file-holder">'+newVal+'</span>');
				} else {
					obj.siblings('.'+options.fakeFileHolderClass+'').text(newVal);
				}
				
				if ((obj.siblings('.'+options.fakeFileHolderClass+'').length > 0) && newVal == '') {
					
					obj.siblings('.'+options.fakeFileHolderClass+'')
					.remove();
					
					obj.siblings('.'+options.buttonClass+'')
					.html(options.defaultText)
					.removeClass()
					.addClass(options.buttonClass+' '+options.additionalButtonClasses);
				}


			});
		});
	};
})(jQuery);