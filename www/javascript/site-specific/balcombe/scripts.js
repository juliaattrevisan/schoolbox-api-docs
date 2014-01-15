$j(function() {

    $j('ul.js_happening li').click(function(){
        calendarPos($j(this),350,50);
    });

    $j('.box ul.js_happening li').click(function(){
        calendarPos($j(this),0,50);
    })

    $j('#tabs li.navthis').prev().css('border', '0');

});