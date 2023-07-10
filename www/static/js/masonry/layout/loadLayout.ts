/**
 * Load the selected layout and insert it into the page
 * @param {string} templateSelectURL - the ID of the layout to load
 * @param {number} layoutId - the ID of the layout to load
 * @returns whether the layout was loaded successfully
 */
async function loadLayout(templateSelectURL: string, layoutId: number) {
    // Include the X-Requested-With header so the server can detect this is an AJAX request
    const response = await fetch(`${templateSelectURL}/${layoutId}`, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });
    const responseData = await response.text();

    document.getElementById("layoutContainer").innerHTML = responseData;
    document.dispatchEvent(new CustomEvent("MASONRY__LAYOUT_LOADED"));
}

document.addEventListener("MASONRY__LOAD_LAYOUT", (event) => {
    loadLayout(event.detail.templateSelectURL, event.detail.layoutId);
});
