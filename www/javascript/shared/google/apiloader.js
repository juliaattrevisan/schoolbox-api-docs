/*! google api loader | (c) 2014 Alaress Pty Ltd | http://www.alaress.com.au
 */
;
(function ($, window, document, undefined) {
    window.loadGoogleApi = function (modules, callback)
    {
        $.ajax({
            url: 'https://apis.google.com/js/api.js',
            dataType: 'script',
            cache: true
        }).then(
            function ()
            {
                var i, j;
                for(i = 0, j = 0; i < modules.length; ++i) {
                    gapi.load(modules[i], function() {
                        if(++j == modules.length && callback) {
                            callback.call(window);
                        }
                    });
                }
            }
        );


    };
})(jQuery, window, document);

