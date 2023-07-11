/**
 * This is used within all Template pages to load the selected layout into the page,
 * ensuring any JS contained within the layout is executed.
 */

import { getLoadedHTML } from "@js/util/domUtils";

/**
 * Load the selected layout and insert it into the page
 * @param {string} templateSelectURL - the URL to load the layout from
 * @returns whether the layout was loaded successfully
 */
async function loadLayout(templateSelectURL: string) {
    // Include the X-Requested-With header so the server can detect this is an AJAX request
    const response = await fetch(templateSelectURL, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });
    const responseData = await response.text();
    const loadedHTML = await getLoadedHTML(responseData);
    document.getElementById("layoutContainer")?.appendChild(loadedHTML);
    document.dispatchEvent(new CustomEvent("MASONRY__LAYOUT_LOADED"));
}

document.addEventListener("MASONRY__LOAD_LAYOUT", (event) => {
    loadLayout((event as CustomEvent).detail.templateSelectURL);
});
