/******* 


This changes file inputs to be prettier! 
I started with this basic converter

http://www.viget.com/inspire/custom-file-inputs-with-a-bit-of-jquery/

and turned it into an eacheriser with options. Now you can mekk pretty things happen probably!

v. 0.1

*******/

(function($){
	$.fn.prettyFileInput = function(options) {
		var defaults = {
			inputHolderClass: 'file-input', 
			buttonClass: 'btn',
			additionalBtnClasses: 'no-marg float-left',
			fakeFileHolderClass: 'file-holder',
			defaultText: '<span></span>Choose file',
			defaultFileSelectedText: '<span></span>File Selected'
			
		};
		var options = $.extend(defaults, options);

		return this.each(function() {
			var obj = $(this);
			
			obj.wrap('<span class="'+options.inputHolderClass+'">');
			obj.after('<span class="'+options.buttonClass+' '+options.additionalBtnClasses+'">'+options.defaultText+'</span>');
			
			obj.bind('change focus click',function() {
				
				$val = obj.val(),
				valArray = $val.split('\\'),
				$button = $('.'+options.buttonClass+''),
				$fakeHolder = $('.'+options.fakeFileHolderClass),
				newVal = valArray[valArray.length-1]

				if(newVal !== '') {
					$button.addClass('tick').html(options.defaultFileSelectedText);
				}
				
				if($fakeHolder.length === 0) {
					$button.after('<span class="'+options.fakeFileHolderClass+'">' + newVal + '</span>');
				} else {
					$fakeHolder.text(newVal);
				}
				

				
			});
		});
	};
})(jQuery);