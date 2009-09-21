(function($) {
		  
	$.fn.recaptcha = function(opts) {

		if (this.length > 1){
			this.each(function() { $(this).recaptcha(opts) });
			return this;
		}

		/* options */
		var defaults = {
			captcha_url	  	: "https://api-secure.recaptcha.net/image?c=",
			challenge_url	: "/ajax/recaptcha.php",
			challenge	  	: "",
			
			/* selectors */
			reload_button 	: "#recaptcha_reload",
			challenge_field : "#recaptcha_challenge",
			answer_field	: "#recaptcha_answer"
		};
		
		var options = $.extend({}, defaults, opts);

		var object = this;
		
		/* gets a new challenge string */
		this.getChallenge = function (callback) {
			$.get(options.challenge_url, '', function (data) {
				options.challenge = data.challenge;

				if ($.isFunction(callback)) callback();
			}, 'json');
		};
		
		/* updates the captcha with a new image */
		this.reloadCaptcha = function (node) {
			$(node).addClass('loading');
			
			object.getChallenge(function () {

				/* update image */
				$('#recaptcha_image').attr('src', options.captcha_url + options.challenge);
				
				/* save new challenge */
				$(options.challenge_field).val(options.challenge);
				
				/* clear text field */
				$(options.answer_field).val('');
				
			});
		};
		
		/* private functions */
		
		var initialize = function (node) {
			
			$(node).addClass('loading');
			$(node).text('');
			
			object.getChallenge(function () {
				
				/* add captcha image */
				$('<img src="' + options.captcha_url + options.challenge + '" alt="" id="recaptcha_image" />').appendTo($(node));
				
				/* show the image each time it finishes loading */
				$('#recaptcha_image').bind('load', function () {
					$(node).removeClass('loading');									  
				});
				
				/* save challenge */
				$(options.challenge_field).val(options.challenge);
				
			});
			
			/* add event to reload button */
			$(options.reload_button).click(function () {
				object.reloadCaptcha($(node));
				return false;
			});

		};

		return initialize(this);
	};
	
	var justSubmit = false;
	
	$.fn.checkCaptcha = function (answer_field, challenge_field, callback) {
	
		var object = $(this);
		
		$(this).submit(function () {
			console.log('xx');
			if (justSubmit)
				return true;
			
			$.get('/ajax/recaptcha.php', 
				{'recaptcha_answer' : $(answer_field).val(), 'recaptcha_challenge' : $(challenge_field).val()},
				function (data) {
					if (data.verified == 'true') {
						justSubmit = true;
						
						object.submit();
					}
						
					else if ($.isFunction(callback)) callback();
				
				}, 'json');
				
			return false;
		
		});
	};


})(jQuery);