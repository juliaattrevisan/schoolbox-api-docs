var $j = jQuery.noConflict();

$j(function() {
	/*
	* Remove the layerX and layerY console log reports from Webkit.
	* */
    var all = $j.event.props,
        len = all.length,
        res = [];
    while (len--) {
      var el = all[len];
      if (el != 'layerX' && el != 'layerY') res.push(el);
    }
    $j.event.props = res



    /*
     * Main Navigation dropdown
     * */
    $j("#nav li").hover(function(){
        $j(this).addClass("hover");
        $j('ul:first',this).toggle()
    }, function(){
        $j(this).removeClass("hover");
        $j('ul:first',this).toggle();
    });

    $j('#nav li:last-child').addClass('lastChild');


	/*
	* Close the calendar popup no matter what!
	* */
	$j('.cal-bubble .close').click(function(){
		$j('.cal-bubble').hide();
	})


	/*
	* Autoclear the main site search field
	* */
	$j('#search-field').autoclear();

 	/*
	* Remove the border from the bottom of the last row of any table.
	* */
	$j('.standardForm tr:last td').css("border", "0") 

	/*
	* Submit the form with a href links with the classes below
	* */
	$j('.submit, .submit-this').click(function(event) {
		var form = $j(this).closest('form');
		if (form.length > 0) {
			event.preventDefault();
			form.submit();
		}
	});

	/*
	* Open rel="external" links ion a new tab.
	* */
	$j('a[rel=external]').attr('target','_blank');

});

/*
* Each skin has a slightly different header height or a different left navigation.
* This function allows us to position the calendar popup at the right place.
* */

function calendarPos(currentObject, leftNegative, heightNegative) {
	$j('.cal-bubble').find('.bottom, .bottom strong').removeAttr("style");
	$j('.cal-bubble').find('.bottom').show();
	var dynamicHTML = $j(currentObject).children('.hidden-fields').html();
	$j('.cal-bubble .middle').children('.js-data').html(dynamicHTML);
	var position = $j(currentObject).offset();
	$j('.cal-bubble').show();
	var annoyingHeight = ($j('.cal-bubble .middle').height() + ($j('.cal-bubble .top').height()) + ($j('.cal-bubble .bottom').height())) 			
	$j('.cal-bubble').css("left", (position.left - leftNegative));
	$j('.cal-bubble').css("top", (position.top - annoyingHeight - heightNegative));
	if (($j('.cal-bubble').css("left")) < '0px') {
			$j('.cal-bubble').css("left", '0');	
	}
	if (($j('.cal-bubble').css("top")) < '0px') {
			$j('.cal-bubble').css("top", '0');	
			$j('.cal-bubble').find('.bottom').hide();
	}
}