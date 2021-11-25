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

    $.fn.ltiComponent = function (params) {
        let actualParams = $.extend({}, defaultParams, params);

        $(this).each(function () {
            let component = $(this);

            component.find('[data-provider]').click(function () {
                let $toaster = actualParams.$toast;
                $toaster.toastActivate({text: 'Loading LTI Provider...'});

                // Display a preloader
                actualParams.$ltiSelectProvider.hide();
                actualParams.$ltiSelectPreloader.show();

                // When a provider is clicked:
                // we need to dynamically update the component and launch the
                // LTI request in its iframe.
                let $this                        = $(this),
                    providerId                   = $this.attr('data-provider'),
                    providerVersion              = $this.attr('data-provider-version'),
                    isProvider1p1                = providerVersion === '1.1',
                    isProvider1p3                = providerVersion === '1.3',
                    providerLaunchUrl            = $this.attr('data-provider-launch-url'),
                    postComponentProviderBaseUrl = actualParams.postComponentProviderBaseUrl,
                    postComponentProviderParams  = {};

                if (isProvider1p3 && providerLaunchUrl) {
                    postComponentProviderParams.url = providerLaunchUrl;
                }

                $.post(
                    postComponentProviderBaseUrl.replace(/providerId/g, providerId),
                    postComponentProviderParams
                ).done(function (result) {
                    // Hide preloader
                    actualParams.$ltiSelectPreloader.hide();

                    $toaster.toastTimeout({text:'Component loaded'});

                    // Update selected provider name in component configuration settings
                    // $('#component{{ componentInstanceId }}-ltiprovider').val(providerId);
                    component.find('input[name="ltiprovider"]').val(providerId);

                    // Update heading
                    component.find('input[name="componentname"]').val(result.providerName);

                    // Update component heading
                    $('#component-title-' + String(actualParams.componentInstanceId)).html(result.providerName);

                    // Launch LTI in the iframe and make it visible
                    let iframe = actualParams.$ltiIframe;

                    // What kind of provider is this?
                    // 1.1: use ltiConsumer.getLaunch
                    // 1.3 with a URL: use ltiPlatformContext.getLaunchResourceLinkRequestForFolder
                    //                 (include custom parameters)
                    // 1.3 with no URL: use ltiPlatformContext.getGenerateDeepLinkingRequestForFolder
                    let launchUrl = '';
                    if (isProvider1p1) {
                        launchUrl = actualParams.lti1p1LaunchUrl;
                    } else if (isProvider1p3) {
                        if (result.url) {
                            launchUrl = actualParams.lti1p3LaunchUrl;
                        } else {
                            launchUrl = actualParams.lti1p3DeepLinkingUrl;
                        }
                    }
                    iframe.attr('src', launchUrl.replace(/providerId/g, providerId));
                    iframe.show();
                }).fail(function () {
                    // This shouldn't happen, but just in case something goes wrong
                    actualParams.$ltiSelectProvider.show();
                    actualParams.$ltiSelectPreloader.hide();
                    actualParams.$toast.toastTimeout({text: 'Unable to load LTI Provider', css: 'alert'});
                });
            });
        });
    };
})(jQuery);
