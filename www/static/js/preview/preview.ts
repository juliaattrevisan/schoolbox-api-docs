import { openFilePreview } from "./previewUtils";

type PreviewOptions = {
    element: HTMLElement | JQuery;
    attribute?: string;
    baseUrl?: string;
};

declare global {
    interface JQuery {
        preview(options: PreviewOptions): void;
    }
}

((): void => {
    /**
     * Constructor
     * @param options Configuration options
     */
    $.fn.preview = function fnPreview(this: HTMLElement | JQuery, options?: Partial<PreviewOptions>): void {
        /**
         * Initialise parameters
         * @param initOptions -
         * @param element -
         * @returns The merged configuration options
         */
        function init(
            initOptions: Partial<PreviewOptions> | undefined,
            element: HTMLElement | JQuery,
        ): {
            element: HTMLElement | JQuery;
            attribute: string;
            baseUrl: string;
        } {
            const defaults = {
                /* The element that activated preview
                 */
                element,

                /* The attribute to analyse
                 */
                attribute: "href",

                /* Base url where preview is located
                 */
                baseUrl: "/preview",
            };

            const derived = {};
            const merged = $.extend(defaults, derived, initOptions);
            return merged;
        }

        /* Store the config in this instance so private methods
         * can access it
         */
        const config = init(options || {}, this);

        /**
         * Preview
         *
         * Only apply to left-click events (though IE can't seem to
         * tell the difference)
         */
        function preview(): void {
            $(config.element)
                .off()
                .on("click", function fnClick(this: HTMLElement, event: JQuery.ClickEvent) {
                    if (event.which > 1) {
                        return;
                    }

                    event.preventDefault();

                    const href = $(this).attr(config.attribute);
                    if (href === undefined) {
                        console.warn(`Unable to open preview, element has no "${config.attribute}" attribute`);
                        return;
                    }

                    void openFilePreview(href, config.baseUrl);
                });
        }

        preview();
    };
})();
