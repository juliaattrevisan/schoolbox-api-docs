$(function () {

    /**
     * Open rel="external" links in a new tab.
     */
    $(document).on('click', 'a[rel=external]', function () {
        $(this).attr('target', '_blank');
    });

    // jQuery datepickers: close on touchstart as well as mousedown
    // (Fastclick stops mousedown from triggering?)
    $(document).on('touchstart', $.datepicker._checkExternalClick);

    // Disable $ formula encoding
    M.$mathQ = false;

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
    $.fn.schoolboxResource_ProxyReplacment();

    /* Notifications toggle
     */
    $('.right-small, .right-off-canvas-menu').hover(function(){
            $('.right-off-canvas-menu').addClass('overlap');
        },
        function(){
            $('.right-off-canvas-menu').removeClass('overlap');
        });

    $('.right-small a, .right-off-canvas-menu label').on('click', function() {
        $('[data-inner-offcanvas]').toggleClass('right-open');
        $(this).toggleClass('raquo');

        var notificationState;
        if ($('[data-inner-offcanvas]').hasClass('right-open')){
            notificationState = 'show';
        } else {
            notificationState = 'hide';
        }
        $.post('/ajax/notificationsTray', {state: notificationState}, function(responseText) { });
    });

    // Left menu dock/undock
    $('#left-menu-dock').on('click', function() {
        // Hide/show the sidebar
        $('[data-offcanvas]').removeClass('move-right');
        $('[data-inner-offcanvas]').toggleClass('left-open');
        $(this).toggleClass('open');

        // Update the hover title on the dock button
        var title = 'Hide navigation';
        if ($(this).hasClass('open')){
            title = 'Show navigation';
        }
        $(this).attr('title', title);

        // Update the user's sidebar state in the DB
        var leftnavState;
        if ($('[data-inner-offcanvas]').hasClass('left-open')) {
            leftnavState = 'show';
        } else {
            leftnavState = 'hide';
        }
        $.post('/ajax/sidenav', {state: leftnavState});
    });

    /* Open / Close account menu
     * + click outside div to close
     */
    var accountMenu = $('#account-content');

    $('#profile-drop').on('click', function(e){
        e.stopPropagation();
        accountMenu.toggleClass('open');
    });

    $('[data-offcanvas]').on('click', function(){
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

});
