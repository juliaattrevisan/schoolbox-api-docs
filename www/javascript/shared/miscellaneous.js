$(function () {

    /**
     * Open rel="external" links in a new tab.
     */
    $(document).on('click', 'a[rel=external]', function () {
        $(this).attr('target', '_blank');
    });

    $('#infoPanel').find('a[data-menu]').flyout();
    $('#nav').touchMenu()
        .scrollToTop({
                cancel: function () {
                    return !$(this).parent().hasClass('has-submenu');
                }
            }
        );

    var s = $('#search');
    s.on('click', '[data-jsubmit]', function () {
        if (s.find('input').val()) {
            s.submit();
        }
    });

    /* Replace all editor video/audio placeholders
    */
    $.fn.schoolboxResource_ProxyReplacement();

    // Notifications dock/undock
    $('#right-menu-dock').on('click', function() {
        var dockState = $(this).data('dock-state');

        // Adjust classes
        if (dockState) {
            $('#inner-wrap').removeClass('right-open');
        } else {
            $('#inner-wrap').addClass('right-open');
        }

        $('#off-canvas-wrap').removeClass('move-left');

        // Update the hover title on the dock button
        var title = dockState ? $(this).data('title-dock') : $(this).data('title-undock');
        $(this).attr('title', title);

        var notificationState = dockState ? 'hide' : 'show';
        $.post('/ajax/notificationsTray', {state: notificationState});

        // Update dock state in the element's data property
        $(this).data('dock-state', !dockState);

        // Update top nav items
        updateTopNavItems();
    });

    // Left menu dock/undock
    $('#left-menu-dock').on('click', function() {
        var dockState = $(this).data('dock-state');

        // Adjust classes
        if (dockState) {
            $('#inner-wrap').removeClass('left-open');
        } else {
            $('#inner-wrap').addClass('left-open');
        }

        $('#off-canvas-wrap').removeClass('move-right');

        // Update the hover title on the dock button
        var title = dockState ? $(this).data('title-dock') : $(this).data('title-undock');
        $(this).attr('title', title);

        // Update the user's sidebar state in the DB
        var leftnavState = dockState ? 'hide' : 'show';
        $.post('/ajax/sidenav', {state: leftnavState});

         // Update dock state in the element's data property
         $(this).data('dock-state', !dockState);

         // Update top nav items
         updateTopNavItems();
    });

    /* Open / Close account menu
     * + click outside div to close
     */
    var accountMenu = $('#account-content');

    $('#profile-drop').on('click', function(e){
        e.stopPropagation();
        accountMenu.toggleClass('open');
    });

    $('#off-canvas-wrap').on('click', function(){
        accountMenu.removeClass('open');
    });

    /* Ensure modals always appear in a visible location
     *
     * The callback open.fndtn.reveal doesn't fire when the
     * modal is built dynamically with js -_-
     *
     * Also, arbitrary 6.25rem padding at top (in the design)
     */
    var modalTop = 6.25*16;

    $(document).on('open.fndtn.reveal', '[data-reveal]', function () {
        $(this).css({'top': $(window).scrollTop() + modalTop + 'px'});
    });

    $(document).on('opened.fndtn.reveal', '[data-reveal]', function () {
        $(this).css({'top': $(window).scrollTop() + modalTop + 'px'});
    });

    var splash, d, x, y, splashTimeout;
    $(document).on('click', ".button, button, .sub-nav a, .pagination a, .flex-list a, [data-component-palette]", function(e) {

        splash = $(this).find(".splash");
        splash.removeClass('animate');
        clearTimeout(splashTimeout);

        if (splash.length === 0) {
            $(this).prepend("<span class='splash'></span>");
        }

        if (!splash.height() && !splash.width()) {
            d = Math.max($(this).outerWidth(), $(this).outerHeight());
            splash.css({height: d, width: d});
        }

        x = e.pageX - $(this).offset().left - splash.width()/2;
        y = e.pageY - $(this).offset().top - splash.height()/2;

        splash.css({top: y+'px', left: x+'px'}).addClass("animate");

        // Remove the animation after a bit of time, otherwise this animation will happen every time the element hides and becomes visible again
        splashTimeout = setTimeout(function() {
            splash.removeClass('animate');
        }, 300);
    });

    /**
     * Autoclear the main site search field (works without polyfill in all but IE9)
     */
    $('#search-field').placeholder();

    // Set up lightboxes globally, group galleries by lightbox relation.
    var lightboxGroups = {};

    $("a[rel^='lightbox']").each(function () {
        var rel = $(this).attr('rel');

        if (!lightboxGroups[rel]) {
            lightboxGroups[rel] = [];
        }

        lightboxGroups[rel].push(this);
    });

    $.each(lightboxGroups, function (i, v) {
        $(v).magnificPopup({
            type: 'image',
            removalDelay: 300,
            mainClass: 'mfp-fade',
            gallery: {
                enabled: true
            }
        });
    });


    /**
     * Hack into Phpdebugbar to add a twig templates button
     */
    var $debugBar = $('.phpdebugbar .phpdebugbar-header-right');
    if ($debugBar.length > 0) {
        // Add the button to the debug bar
        var $html =
            '<span class="phpdebugbar-indicator">' +
                '<a id="showTwigNames">' +
                    '<i class="phpdebugbar-fa phpdebugbar-fa-leaf" style="color:#555"></i>' +
                '</a>' +
                '<span class="phpdebugbar-tooltip">Twig templates</span>' +
            '</span>'
        ;
        $debugBar.find('.phpdebugbar-indicator').first().before($html);

        // Toggle twig template names showing on click of the button
        $('#showTwigNames').click(function () {
            if ($('.twigName').attr('hidden')) {
                $('.twigName').removeAttr('hidden');
            } else {
                $('.twigName').attr('hidden', true);
            }
        });
    }

    /**
     * Show/hide top nav items based on space available
     */
    function updateTopNavItems() {
        var topMenuItemWidth = $('#top-menu > li').width();

        // All the possible things to remove from the #top-menu width
        var profileWidth = $('#profile-drop').is(':visible') ? document.getElementById('profile-drop').offsetWidth : 0;
        var searchWidth = $('#search').is(':visible') ? document.getElementById('search').offsetWidth : 0;
        var notificationsWidth = $('#notifications').is(':visible') && $('#message-list').not(':visible') ? document.getElementById('notifications').offsetWidth : 0;
        // ^ have to check that the notfications bar isn't docked as well, if it is- it's not part of the #top-menu width
        var extraWidth = profileWidth + searchWidth + notificationsWidth;

        // Store the width left in top nav after subtracting
        var spaceLeft = $('#top-menu').width() - extraWidth;

        $('#top-menu > li').each(function(index) {
            // Always show the first 4 on the top nav
            if (index < 4) {
                toggleTopNavItem(true, $(this));

                // Remove the space used from the 'spaceLeft' var
                spaceLeft -= topMenuItemWidth;

                return;
            }

            if (spaceLeft > topMenuItemWidth) { // if there's space
                toggleTopNavItem(true, $(this));

                // Remove the space used from the 'spaceLeft' var
                spaceLeft -= topMenuItemWidth;
            } else {
                toggleTopNavItem(false, $(this));
            }
        });
    }

    /**
     * Show a top nav item, and hide from the sidebar
     *
     * @param {boolean} show
     * @param {Object} $item
     */
    function toggleTopNavItem(show, $item) {
        var itemId = $item.data('id');

        // Show top nav
        show ? $item.show() : $item.hide();

        // Hide this item from the sidebar
        var $sidebarItem = $('#overflow-nav li[data-id=' + itemId + ']');
        show ? $sidebarItem.hide() : $sidebarItem.show();
    }

    // Run the top nav update on page load
    updateTopNavItems();

    // Listen for changes to window size and run the top nav update
    // Apply a timeout so that the function doesn't run a million times while resizing
    var resizeTimeout;
    $(window).resize(function() {
        this.clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateTopNavItems, 100);
    });
});
