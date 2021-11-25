(function ($) {
    // LTI component selector
    let defaultParams = {
        // The component itself: comes from $(this)
        // $component: $(),

        // The component instance ID
        componentInstanceId: null,

        // The toaster
        $toast: $(),

        // The place in which the select buttons live
        $ltiSelectProvider: $(),

        // The preloader: displays when waiting for something to happen
        $ltiSelectPreloader: $(),

        // The LTI iframe
        $ltiIframe: $(),

        // Provider ID placeholder: required when generating a URL when the LTI
        // tool is not yet selected
        providerIdPlaceholder: '',

        // URLS:

        // The URL to post to to update the component's configuration
        postComponentProviderBaseUrl: '',

        // The launch URL for LTI 1.1 components (basic or picker).
        // All URLs may contain a "providerId" placeholder.
        lti1p1LaunchUrl: '',

        // The launch URL for LTI 1.3 components
        lti1p3LaunchUrl: '',

        // The deep linking URL for LTI 1.3 components
        lti1p3DeepLinkingUrl: '',
    };

    /**
     * Constructor
     *
     * @param {jQuery} $element
     * @param {Object} params
     * @constructor
     */
    let LtiComponent = function($element, params) {
        this.$element = $element;
        this.params   = $.extend({}, defaultParams, params);
    };

    /**
     * Set up the LTI component's listeners.
     */
    LtiComponent.prototype.setupListeners = function () {
        let self = this;
        self.$element.find('[data-provider]').on('click.ltiComponent', function () {
            // When a provider is clicked:
            // we need to dynamically update the component and launch the LTI
            // request in its iframe.
            let $button                      = $(this),
                params                       = self.params,
                providerId                   = $button.attr('data-provider'),
                providerVersion              = $button.attr('data-provider-version'),
                isProvider1p1                = providerVersion === '1.1',
                isProvider1p3                = providerVersion === '1.3',
                providerLaunchUrl            = $button.attr('data-provider-launch-url'),
                postComponentProviderBaseUrl = params.postComponentProviderBaseUrl,
                postComponentProviderParams  = {};

            // Display toast
            params.$toast.toastActivate({text: 'Loading LTI Provider...'});

            // Display a preloader
            params.$ltiSelectProvider.hide();
            params.$ltiSelectPreloader.show();

            // Inject the launch URL if there is one:
            // this bypasses content selection and displays the resource link
            // directly.
            if (isProvider1p3 && providerLaunchUrl) {
                postComponentProviderParams.url = providerLaunchUrl;
            }

            // Post and process the result
            $.post(
                postComponentProviderBaseUrl.replace(
                    params.providerIdPlaceholder,
                    providerId
                ),
                postComponentProviderParams
            ).done(function (result) {
                // Hide preloader
                params.$ltiSelectPreloader.hide();

                params.$toast.toastTimeout({text:'Component loaded'});

                // Update selected provider name in component configuration settings
                self.$element.find(':input[name="ltiprovider"]').val(providerId);

                // Update heading
                self.$element.find(':input[name="componentname"]').val(result.providerName);

                // Update component heading
                $('#component-title-' + String(params.componentInstanceId)).html(result.providerName);

                // What kind of provider is this?
                // 1.1: use ltiConsumer.getLaunch
                // 1.3 with a URL: use ltiPlatformContext.getLaunchResourceLinkRequestForFolder
                //                 (include custom parameters)
                // 1.3 with no URL: use ltiPlatformContext.getGenerateDeepLinkingRequestForFolder
                let launchUrl = '';
                if (isProvider1p1) {
                    launchUrl = params.lti1p1LaunchUrl;
                } else if (isProvider1p3) {
                    if (result.url) {
                        launchUrl = params.lti1p3LaunchUrl;
                    } else {
                        launchUrl = params.lti1p3DeepLinkingUrl;
                    }
                }

                // Launch LTI in the iframe and make it visible
                params.$ltiIframe.attr(
                    'src',
                    launchUrl.replace(params.providerIdPlaceholder, providerId)
                );
                params.$ltiIframe.show();
            }).fail(function () {
                // This shouldn't happen, but just in case something goes wrong
                params.$ltiSelectProvider.show();
                params.$ltiSelectPreloader.hide();
                params.$toast.toastTimeout({text: 'Unable to load LTI Provider', css: 'alert'});
            });
        });
    }

    $.fn.ltiComponent = function (params) {
        // let actualParams = $.extend({}, defaultParams, params);
        $(this).each(function () {
            let $component      = $(this),
                componentObject = new LtiComponent($component, params);
            $component.data('ltiComponent', componentObject);
            componentObject.setupListeners();
        });
    };
})(jQuery);
