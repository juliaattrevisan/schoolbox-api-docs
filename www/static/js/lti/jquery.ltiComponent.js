(function ($) {
    // LTI component selector
    let defaultParams = {
        // The component itself: comes from $(this)
        // $component: $(),

        // The component instance ID
        componentInstanceId: null,

        // The component title bar:
        // update this with the selected LTI content's title
        $componentTitle: $(),

        // The toaster
        $toast: $(),

        // The place in which the select buttons live
        $ltiSelectProvider: $(),

        // The preloader: displays when waiting for something to happen
        $ltiSelectPreloader: $(),

        // The LTI link:
        // if the selected provider launches in a window, show this instead of
        // the iframe when content is selected.
        $ltiLink: $(),

        // Text to display in the LTI link before content is selected
        linkTextBeforeContentSelected: "Select content to embed",

        // Text to display in the LTI link after content is selected
        linkTextAfterContentSelected: "Launch embedded tool",

        // The LTI iframe:
        // show the content selector in here, as well as the selected content
        // (if the provider launches in an iframe).
        $ltiIframe: $(),

        // Provider ID placeholder: required when generating a URL when the LTI
        // tool is not yet selected
        providerIdPlaceholder: "",

        // Deep linking response message type:
        // defaults to LTI 1.3 deep linking response message type
        deepLinkingResponseMessageType: "LtiDeepLinkingResponse",

        // URLS:

        // The URL to post to to update the component's configuration
        postComponentProviderBaseUrl: "",

        // The launch URL for LTI 1.1 components (basic or picker).
        // All URLs may contain a "providerId" placeholder.
        lti1p1LaunchUrl: "",

        // The launch URL for LTI 1.3 components
        lti1p3LaunchUrl: "",

        // The deep linking URL for LTI 1.3 components
        lti1p3DeepLinkingUrl: "",
    };

    /**
     * Constructor
     *
     * @param {jQuery} $element
     * @param {Object} params
     * @constructor
     */
    let LtiComponent = function ($element, params) {
        this.$element = $element;
        this.params = $.extend({}, defaultParams, params);
    };

    /**
     * Build a component context.
     *
     * This will be used in `setupMessageListener`, to filter out messages
     * raised by other components.
     *
     * @return {String}
     */
    LtiComponent.prototype.buildComponentContext = function () {
        return "componentInstance-" + String(this.params.componentInstanceId);
    };

    /**
     * Event for clicking of an LTI provider button.
     *
     * This event:
     * * dynamically updates the component
     * * launches the LTI request
     *
     * @param {jQuery} $button
     */
    LtiComponent.prototype.onClickProvider = function ($button) {
        let self = this,
            params = self.params,
            providerId = $button.attr("data-provider"),
            providerVersion = $button.attr("data-provider-version"),
            isProvider1p1 = providerVersion === "1.1",
            isProvider1p3 = providerVersion === "1.3",
            providerTarget = $button.attr("data-provider-target"),
            isProviderTargetIframe = providerTarget === "iframe",
            isProviderTargetWindow = providerTarget === "window",
            providerLaunchUrl = $button.attr("data-provider-launch-url"),
            postComponentProviderBaseUrl = params.postComponentProviderBaseUrl,
            postComponentProviderParams = {};

        // Display toast
        params.$toast.toastActivate({ text: "Loading LTI Provider..." });

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
            postComponentProviderBaseUrl.replace(params.providerIdPlaceholder, providerId),
            postComponentProviderParams,
        )
            .done(function (result) {
                // Hide preloader
                params.$ltiSelectPreloader.hide();

                params.$toast.toastTimeout({ text: "Component loaded" });

                // Update selected provider name in component configuration settings
                self.$element.find(':input[name="ltiprovider"]').val(providerId);

                // Update heading
                self.$element.find(':input[name="componentname"]').val(result.providerName);

                // Update URL (even if it's empty)
                self.$element.find(':input[name="url"]').val(result.url);

                // Update component heading
                params.$componentTitle.text(result.providerName);

                // What kind of provider is this?
                // 1.1: use ltiConsumer.getLaunch
                // 1.3 with a URL: use ltiPlatformContext.getLaunchResourceLinkRequestForFolder
                //                 (include custom parameters)
                // 1.3 with no URL: use ltiPlatformContext.getGenerateDeepLinkingRequestForFolder
                let launchUrl = "";
                if (isProvider1p1) {
                    launchUrl = params.lti1p1LaunchUrl;
                } else if (isProvider1p3) {
                    if (result.url) {
                        launchUrl = params.lti1p3LaunchUrl;
                    } else {
                        launchUrl = params.lti1p3DeepLinkingUrl;
                    }
                }

                // Launch LTI in the iframe/link and make it visible
                // Add context param too
                launchUrl = new URL(launchUrl.replace(params.providerIdPlaceholder, providerId), window.location);
                launchUrl.searchParams.append("context", self.buildComponentContext());
                if (isProviderTargetIframe) {
                    params.$ltiIframe.attr("src", launchUrl.toString());
                    params.$ltiIframe.show();
                } else if (isProviderTargetWindow) {
                    params.$ltiLink
                        .attr("href", launchUrl.toString())
                        .text(params.linkTextBeforeContentSelected)
                        .show();
                    params.$ltiLink.parent().show();
                }
            })
            .fail(function () {
                // This shouldn't happen, but just in case something goes wrong
                params.$ltiSelectProvider.show();
                params.$ltiSelectPreloader.hide();
                params.$toast.toastTimeout({ text: "Unable to load LTI Provider", css: "alert" });
            });
    };

    /**
     * Set up the LTI component's listener.
     */
    LtiComponent.prototype.setupClickListener = function () {
        let self = this;
        self.$element.on("click.ltiComponent", "[data-provider]", function () {
            self.onClickProvider($(this));
        });
    };

    /**
     * Set up the LTI component's message listener, for when a deep link is
     * selected.
     */
    LtiComponent.prototype.setupMessageListener = function () {
        let self = this;
        window.addEventListener("message", (event) => {
            // Only process this message if:
            // * it has data
            // * its message type is a deep linking response
            // * its context is appropriate for this component
            //   (required so that multiple LTI components don't trigger the
            //    same event)
            if (event.data) {
                // Fill in the component with values from the message data
                let data = event.data,
                    context = data?.context,
                    tool = data?.tool,
                    messageType = data?.messageType,
                    resourceLink = data?.resourceLinks?.[0];

                // Not a deep linking response: ignore this message
                if (
                    messageType === self.params.deepLinkingResponseMessageType &&
                    context === self.buildComponentContext()
                ) {
                    // Look at the first resource link, set values based on that
                    if (resourceLink) {
                        let url = resourceLink?.url ?? tool?.launchUrl;
                        if (url) {
                            // Select the deep link, otherwise cancel?
                            self.deepLinkSelect(tool?.id, url, resourceLink);
                        }
                    } else {
                        // Just reload the deep linking launch URL
                        self.deepLinkCancel(tool.id);
                    }
                }
            }
        });
    };

    /**
     * Select a deep link.
     *
     * @param {Number} providerId
     * @param {String} url
     * @param {Object} resourceLink
     */
    LtiComponent.prototype.deepLinkSelect = function (providerId, url, resourceLink) {
        // Post to the component configuration
        let params = this.params,
            postComponentProviderBaseUrl = params.postComponentProviderBaseUrl,
            postComponentProviderParams = {
                url: url,
                description: JSON.stringify(resourceLink?.custom ?? {}),
                componentname: resourceLink?.title,
            };

        // Update URL and custom fields (even if empty)
        this.$element.find(':input[name="componentname"]').val(postComponentProviderParams.componentname);
        this.$element.find(':input[name="description"]').val(postComponentProviderParams.description);
        this.$element.find(':input[name="url"]').val(url);

        // Update title
        params.$componentTitle.text(postComponentProviderParams.componentname);

        $.post(
            postComponentProviderBaseUrl.replace(params.providerIdPlaceholder, String(providerId)),
            postComponentProviderParams,
        ).done(function () {
            let launchUrl = params.lti1p3LaunchUrl.replace(params.providerIdPlaceholder, String(providerId));
            // TODO determine whether to update the link or iframe?
            params.$ltiIframe.attr("src", launchUrl);
            params.$ltiLink.attr("href", launchUrl).text(params.linkTextAfterContentSelected);
        });
    };

    /**
     * Cancel the deep link selection.
     *
     * @param {Number} providerId
     */
    LtiComponent.prototype.deepLinkCancel = function (providerId) {
        let deepLinkingUrl = this.params.lti1p3DeepLinkingUrl.replace(
            this.params.providerIdPlaceholder,
            String(providerId),
        );
        // TODO determine whether to update the link or iframe?
        this.params.$ltiIframe.attr("src", deepLinkingUrl);
        this.params.$ltiLink.attr("href", deepLinkingUrl).text(this.params.linkTextBeforeContentSelected);
    };

    /**
     * Find all LTI component objects in `$elements`.
     *
     * @param {jQuery} $elements
     * @return {LtiComponent[]}
     */
    LtiComponent.findLtiComponents = function ($elements) {
        let result = [];
        $($elements).each(function () {
            let ltiComponent = $(this).data("ltiComponent");
            if (ltiComponent instanceof LtiComponent) {
                result.push(ltiComponent);
            }
        });
        return result;
    };

    /**
     * jQuery plugin for setting up LTI components.
     *
     * @param {Object} params
     */
    $.fn.ltiComponent = function (params) {
        $(this).each(function () {
            let $component = $(this),
                componentObject = new LtiComponent($component, params);
            $component.data("ltiComponent", componentObject);
            componentObject.setupClickListener();
            componentObject.setupMessageListener();
        });
    };

    /**
     * jQuery plugin for selecting a deep link.
     *
     * @param {Number} providerId
     * @param {String} url
     * @param {Object} resourceLink
     */
    $.fn.ltiComponentDeepLinkSelect = function (providerId, url, resourceLink) {
        LtiComponent.findLtiComponents(this).forEach((v) => v.deepLinkSelect(providerId, url, resourceLink));
    };

    /**
     * jQuery plugin for cancelling a deep link selection.
     *
     * @param {Number} providerId
     */
    $.fn.ltiComponentDeepLinkCancel = function (providerId) {
        LtiComponent.findLtiComponents(this).forEach((v) => v.deepLinkCancel(providerId));
    };
})(jQuery);
