/**
 * Colour configuration used by editColumns.
 */
interface Colours {
    defaults: ColourProperties;
    original: ColourProperties;
    pickers: ColourProperties;
}

/**
 * Colour properties for different elements.
 */
interface ColourProperties {
    top_head_font: string;
    top_head_bg: string;
    top_font_primary: string;
    top_bg_primary: string;
    left_head_font: string;
    left_head_bg: string;
    left_font_primary: string;
    left_bg_primary: string;
    right_head_font: string;
    right_head_bg: string;
    right_font_primary: string;
    right_bg_primary: string;
}

/**
 * Configuration object for Masonry.
 */
interface MasonryConfig {
    settings: {
        // Main settings for the page
        allowEditing: boolean; // Editing is allowed on the page (true/false)
        existingComponentSelector: string; // Selector for existing components ('[data-component]')
        layoutSelector: string; // Selector for the layout container ('#component-layout')
        updateUrl: string; // URL to send updates to ('/homepage/configForm.php')
        refreshPaletteCountsOnLoad?: boolean; // (Optional) Should the palette counts be refreshed on page load? (true/false) - The admin template backend doesn't give us palette counts, so we need to do this.
        formData: {
            // Some pages have differing form data requirements
            pageInfo: {
                type: "cid" | "page_id"; // Key for the page ID ('cid' on homepages, 'page_id' on eportfolio)
                id: string; // Value for the page ID (80)
                context?: string; // (Optional) Used on admin templates to distinguish between different pages ('dashboard', 'homepage', 'eportfolio')
            };
            newComponentAddAttribute: string; // Attribute of a component which includes the new component definition id to know which type of component to create ('data-component-definition-id')
            newComponentFromAttribute?: string; // (Optional) ePortfolio requires a "from" attribute representing the component ID ('data-component-id')
        };
    };
    editColumns?: {
        // (Optional) Exclude this if the page doesn't need column editing, for example on admin templates
        columnWidthSelector: string; // Selector for the column width input ('[data-interaction="column-width"]')
        leftContainerSelector: string; // Selector for the left component container ('[data-position="left"]')
        rightContainerSelector: string; // Selector for the right component container ('[data-position="right"]')
        colourPickersSelector: string; // Selector for colour picker inputs ('[data-colorpicker]')
        colours: Colours; // Global variable to use for tracking the colour pickers
    };
    postToMyLinks?: {
        // (Optional) This adds functionality for the "Add to My Links" button on homepages
        selector: string; // Selector for the button ('#postToMyLinks')
        url: string; // URL to add ('/homepage/80')
        heading: string; // Title for the link ('9A English (9ENGA)')
        updateUrl: string; // URL to send the request to ('/cms/myLinksForm.php')
    };
    toggleLockComponents?: {
        // (Optional) This adds functionality for the toggle switch for locking compenent editing
        selector: string; // Selector for the switch ('#component-lock-switch input')
    };
}

let masonryConfig: MasonryConfig | null = null;

/**
 * Returns the current Masonry configuration.
 * @returns - The current Masonry configuration.
 */
export function getMasonryConfig(): MasonryConfig | null {
    return masonryConfig;
}

/**
 * Sets the Masonry configuration.
 * @param config - The configuration to set.
 */
export function setMasonryConfig(config: MasonryConfig): void {
    masonryConfig = config;
}
