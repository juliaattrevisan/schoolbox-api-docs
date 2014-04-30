$j(function() {

    /* left tree navigation */
    $j('#tree').tree({
        type	 : 'table',
        node 	 : 'tr',
        onOff	 : 'dark',
        sequencing	: false,
        dragDrop : false
    });

    /**
     * Click event for selecting a folder - triggers the actual select node event.
     */
    $j('#tree').on('click.tree', 'tr:not(.server)', function() {
        $j(this).trigger('selectNode.tree');
    });

    /**
     * Select node event
     */
    $j('#tree').on('selectNode.tree', 'tr:not(.server)', function() {
        /* select folder */
        $j('#tree .selected').removeClass('selected');
        $j(this).addClass('selected');

        var relationID = $j(this).attr('id').substring(5);

        $j.post('/ajax/tree.php', {resourcesID : relationID});

        $j('#destination').val(relationID);
        $j('#destination-text').val($j(this).find('.label').text());
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

    $j('#category-window, #file-window').height($j(window).height()-$j('#header').height()-$j('#footer').height()-50);

    $j(window).resize(function() {
        $j('#category-window, #file-window').height($j(window).height()-$j('#header').height()-$j('#footer').height()-50)
    });

});

