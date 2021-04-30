/*! jquery.gpick.js | (c) 2019 Schoolbox Pty Ltd | https://schoolbox.com.au/
 */
;
(function ($, window, document, auth, picker, undefined) {
    var noop = function () {},
        pluginName = 'gpick',
        defaults = {
            // creds
            developerKey: '',
            clientId: '',
            scopes: [],

            // interface
            authBtn: '',
            authNeededClass: '',

            // callbacks
            onCancel: noop,
            onSelect: noop,
            onLoaded: noop,

            // exposed picker options
            title: '',
            features: [],
            views: [picker.ViewId.DOCS],
            relayUrl: null,
            origin: null,
            allowedTypes: null
        };


    function GPick(element, options) {

        this.element = element;
        this.options = $.extend({}, defaults, options);
        this.oauthToken = null;
        this.pickerInstance = null;

        this.authorize(true);
    }


    GPick.prototype.build = function () {
        var self = this, i, o, builder;

        if (!this.oauthToken) {
            return false;
        }

        o = this.options;
        builder = new picker.PickerBuilder();

        // conditionally set or hide title
        if (o.title === null) {
            builder.hideTitleBar();
        }
        else if (o.title !== '') {
            builder.setTitle(o.title);
        }

        // enable selected features
        for (i = 0; i < o.features.length; ++i) {
            builder.enableFeature(o.features[i]);
        }

        // enabled selected views
        for (i = 0; i < o.views.length; ++i) {
            builder.addView(o.views[i]);
        }

        builder
            .setOAuthToken(this.oauthToken.access_token)
            .setCallback(function (data) {
                self.handleResponse(data);
            });

        this.pickerInstance = builder.build();

        return true;
    };

    GPick.prototype.show = function () {
        if(!this.pickerInstance) {
            if(!this.build()) {
                return;
            }
        }
        this.pickerInstance.setVisible();
    };


    GPick.prototype.handleResponse = function (data) {
        var elem = $(this.element);

        if (data[picker.Response.ACTION] === picker.Action.LOADED) {
            this.options.onLoaded.call(elem, this.pickerInstance);
            return;
        }

        if (data[picker.Response.ACTION] === picker.Action.PICKED) {
            this.options.onSelect.call(elem, data.docs, this.oauthToken);
            return;
        }

        if (data[picker.Response.ACTION] === picker.Action.CANCEL) {
            this.options.onCancel.call(elem);
        }
    };


    GPick.prototype.handleAuthResult = function(authResult) {
        var authBtn = $(this.options.authBtn);
        var self = this;

        if (authResult && !authResult.error) {
            this.oauthToken = authResult;
            this.show();
            authBtn.removeClass(this.options.authNeededClass);
        }
        else {
            authBtn
                .addClass(this.options.authNeededClass)
                .on('click', function (e)
                {
                    e.preventDefault();
                    self.authorize(false);
                }
            );
        }
    };


    GPick.prototype.authorize = function() {
        var self = this;
        auth.authorize({
                'client_id': this.options.clientId,
                'scope': this.options.scopes.join(' ')
            },
            function (authResult)
            {
                self.handleAuthResult(authResult);
            }
        );
    };


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new GPick(this, options));
            }
        });
    };

})(jQuery, window, document, window.gapi.auth2, window.google.picker);
