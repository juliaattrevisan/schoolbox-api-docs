$(function() {
    // auto TOC
    var toc = $('<ol id="toc" style="padding:0 1rem;" />');

    $('#content').find('h1').each(function () {
        var h1 = $(this), id;

        if ('undefined' == typeof h1.attr('id')) {
            // set an ID
            id = h1.text().trim().toLowerCase().replace(/[^a-z0-9]+/ig, '-');

            h1.attr('id', id);
        }
        else {
            id = h1.attr('id');
        }

        toc.append($('<li/>').html(
            $('<a />').text(h1.text()).attr('href', '#' + id)
        ));

        if (h1.children('a.permalink').length == 0) {
            // add a permalink
            h1.append($('<a style="opacity:0.2;margin-left:1rem;" class="permalink">#</a>').attr('href', '#' + h1.attr('id')));
        }
    });

    $('aside.right-off-canvas-menu').append($('<h2 class="subheader" style="padding:0;margin-top:1rem;min-height:1rem;"/>').text('Pantry TOC')).append(toc);

    $('#toc').on('click', 'a', function (e) {

        e.preventDefault();

        var target = this.href.split('#')[1],
            top    = $('#' + target).offset().top;

        $('html, body').animate({scrollTop: top}, 700, 'easeInOutSine');

        $('.off-canvas-wrap').removeClass('move-left');

    });
});
