$j(function() {

    /* left tree navigation */
    $j('#tree').tree({
        type	 : 'table',
        node 	 : 'tr',
        onOff	 : 'dark',
        sequencing	: false
    });




    /* load right panel file list */
    $j('#tree tr:not(.server)').live('click', function () {

        /* select folder */
        $j('#tree .selected').removeClass('selected');
        $j(this).addClass('selected');

        var relationID = $j(this).attr('id').substring(5);

        console.log(relationID);

        /* remember tree branch */
        $j.post('/ajax/tree.php', {relationID : relationID}, function(data) {

            console.log(data);

            var table = $j('#tree'),
                scroller = $j('#category-window div.scroller');

            /* fix left panel width */
            var scrollerWidth = parseInt(scroller.width()) - 1;
            if (table.height() > scroller.height())
                scrollerWidth -= 16; // scroller width

            if (table.width() < scrollerWidth)
                table.width(scrollerWidth);
            else {
                var maxWidth = 0, width;
                table.find('tr').each(function () {
                    width = 0;
                    table.find('span').each(function () {
                        width += $j(this).width();
                    });
                    width += 39 ; // cell padding, highlight border
                    if (width > maxWidth)
                        maxWidth = width;
                });
                if (maxWidth > scrollerWidth)
                    table.width(maxWidth);
            }



        });

        $j('#destination').val(relationID);
    });

    /* get resourcesID */
    var resourcesID = 0, selected = $j('#tree tbody tr.selected');
    if (selected.length > 0)
        resourcesID = selected.attr('id').substring(5);






    /* triggered after a new branch is loaded */
    $j('#tree').bind('loaded', function () {
        var table = $j(this), scroller = $j('#category-window div.scroller');

        /* fix left panel width */
        var scrollerWidth = parseInt(scroller.width()) - 1;
        if (table.height() > scroller.height())
            scrollerWidth -= 16; // scroller width

        if (table.width() < scrollerWidth)
            table.width(scrollerWidth);
        else {
            var maxWidth = 0, width;
            table.find('tr').each(function () {
                width = 0;
                $j(this).find('span').each(function () {
                    width += $j(this).width();
                });
                width += 39 ; // cell padding, highlight border
                if (width > maxWidth)
                    maxWidth = width;
            });
            if (maxWidth > scrollerWidth)
                table.width(maxWidth);
        }

    });






    $j('#tree').triggerHandler('loaded');

    /* resize left column on window resize */
    $j(window).resize(function () {
        $j('#tree').triggerHandler('loaded');
    });

    $j("div.loading").hide();

    /* auto scroll to open branch */
    $j("#category-window div.scroller").scrollTo($j("#node-0"), 200);

    $j('#category-window, #file-window').height($j(window).height()-$j('#header').height()-$j('#footer').height()-50);

    $j(window).resize(function() {
        $j('#category-window, #file-window').height($j(window).height()-$j('#header').height()-$j('#footer').height()-50)
    });


});

