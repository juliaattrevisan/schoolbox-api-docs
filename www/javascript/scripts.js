var $j = jQuery.noConflict();
/*-------------------------------------------------------------------------*/
$j(function externalLinks() {
 if (!document.getElementsByTagName) return;
 var anchors = document.getElementsByTagName("a");
 for (var i=0; i<anchors.length; i++) {
   var anchor = anchors[i];
   if (anchor.getAttribute("href") &&
       anchor.getAttribute("rel") == "external")
     anchor.target = "_blank";
 }
});
/*-------------------------------------------------------------------------*/
$j(function() {
	
	$j('#nav ul').droppy({speed: 0});
	
	$j('#search-field').autoclear();
	
	$j('.standardForm tr:last td').css("border", "0") 
	
	$j('.submit').click(function(event) {
		event.preventDefault();
		$j(this).closest('form').submit();
	});

	

});

function calendarPos(currentObject, leftNegative, heightNegative) {
	$j('.cal-bubble').find('.bottom').removeAttr("style");
	$j('.cal-bubble').find('.bottom strong').removeAttr("style");
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