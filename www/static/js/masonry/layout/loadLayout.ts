/**
 * This is used within all Template pages to load the selected layout into the page,
 * ensuring any JS contained within the layout is executed.
 */

import { getLoadedHTML } from "@js/util/domUtils";

/**
 * Load the selected layout and insert it into the page
 * @param templateSelectURL - the URL to load the layout from
 * @returns Resolves and fires a MASONRY__LAYOUT_LOADED event when the layout is loaded
 */
async function loadLayout(templateSelectURL: string): Promise<void> {
    // Include the X-Requested-With header so the server can detect this is an AJAX request
    const response = await fetch(templateSelectURL, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });
    const responseData = await response.text();
    const loadedHTML = await getLoadedHTML(responseData);
    const layoutContainer = document.getElementById("layoutContainer");

    if (layoutContainer !== null && loadedHTML !== null) {
        layoutContainer.appendChild(loadedHTML);
    }

    document.dispatchEvent(new CustomEvent("MASONRY__LAYOUT_LOADED"));
}

interface MasonryLoadLayoutEvent extends CustomEvent {
    detail: {
        templateSelectURL: string;
    };
}

document.addEventListener("MASONRY__LOAD_LAYOUT", (event) => {
    void loadLayout((event as MasonryLoadLayoutEvent).detail.templateSelectURL);
});
