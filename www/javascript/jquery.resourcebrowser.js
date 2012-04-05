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

        /* hide all files */
        $j('#file-list tbody tr').hide();

        var relationID = $j(this).attr('id').substring(5);
        var files = $j('tr.category-' + relationID);

        if (files.length > 0) {
            /* files exist, show them */
            files.show();

            /* re-apply on off */
            $j('#file-list tbody tr').removeClass('dark');
            $j('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');

            /* remember tree branch */
            $j.post('/ajax/tree.php', {resourcesID : relationID});

        } else {

            /* ajax load files */
            $j.post('/ajax/file.php', {categoryID : relationID}, function (data) {

                if (data.error) {
                    alert(data.error);
                } else if (data.length == 0) {
                    /* display empty message */
                    $j('<tr class="category-' + relationID + ' empty"><td>No files to display</td></tr>').appendTo($j('#file-list'));

                } else {
                    var template = $j('#file-template');
                    for (i in data) {
                        var row = data[i];

                        /* build new file (row) */
                        var clone = template.clone();
                        clone.attr('id', 'file-' + row.id);
                        clone.find('span.file').text(row.description);
                        clone.find('td:eq(1)').text(row.user);
                        clone.find('td:eq(2)').text(row.relative);
                        clone.addClass('category-' + relationID);
                        clone.addClass(row.mimeClass);

                        if (row.url)
                            clone.addClass('link');

                        if (row.hidden == 1)
                            clone.addClass('hidden');

                        clone.appendTo($j('#file-list')).show();
                    }
                }

                /* re-apply on off */
                $j('#file-list tbody tr').removeClass('dark');
                $j('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');

                initialiseFiles(relationID);

            }, 'json');
        }

        $j('#destination').val(relationID);

    });

    var initialiseFiles = function (relationID) {

        var newChildren = $j('tr.category-' + relationID + ':not(.empty)'), categoryNode = $j('#node-' + relationID);

        /* apply file context menus */
        newChildren.contextMenu(fileMenu, {
            theme: 'vista',
            beforeShow: function (event) {

                /* un-select selection if clicked outside */
                if (!$j(this.target).hasClass('selected'))
                    $j(this.target).triggerHandler('click', event);

                var categoryID = $j(this.target)[0].className.match(/category-([\d]+)/), folder = $j('#node-' + categoryID[1].toString())

                /* enable all options */
                $j(this.menu).find('.context-menu-item').removeClass('context-menu-item-disabled');

                if ($j(this.target).hasClass("link"))
                    $j($j(this.menu).find('.context-menu-item')[1]).toggleClass("context-menu-item-disabled", true);

                if (!folder.hasClass("write") && !folder.hasClass("full")) {
                    // disable from modify to new folder
                    for (var i = 2; i <= 7; i++)
                        $j($j(this.menu).find('.context-menu-item')[i]).toggleClass("context-menu-item-disabled", true);
                }
                else if ($j('#file-list tr.selected').length > 1) {
                    $j(this.menu).find('.context-menu-item').addClass('context-menu-item-disabled');
                    $j(this.menu).find('.context-menu-item:eq(3)').removeClass('context-menu-item-disabled');
                }

            }
        });

        /* double click files, default action */
        newChildren.dblclick(function () {
            if ($j(this).hasClass("link"))
                window.open("/redir.php?id="+$j(this).attr("id").substring(5),"_blank");
            else
                window.open("/send.php?id="+$j(this).attr("id").substring(5),"_blank");
        });

        /* select files */
        newChildren.click(function (event) {

            /* allow multiple select */
            if (!event.ctrlKey && !event.shiftKey)
                $j('#file-list tr.selected').removeClass('selected');

            if (event.ctrlKey)
                $j(this).toggleClass('selected');
            else
                $j(this).addClass('selected');

            /* select range */
            if (event.shiftKey) {

                var node1 = $j('#file-list tr').index($j('#file-list tr.current'));
                var node2 = $j('#file-list tr').index(this);

                if (node1 > node2)
                    $j('#file-list tr').slice(node2, node1).addClass('selected');
                else
                    $j('#file-list tr').slice(node1, node2).addClass('selected');
            }

            $j('#file-list tr.current').removeClass('current');
            $j(this).addClass('current');

        });
    };

    /* get resourcesID */
    var resourcesID = 0, selected = $j('#tree tbody tr.selected');
    if (selected.length > 0)
        resourcesID = selected.attr('id').substring(5);

    initialiseFiles(resourcesID);

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