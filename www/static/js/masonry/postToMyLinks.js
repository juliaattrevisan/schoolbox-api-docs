/**
 * Adds this page to my links
 *
 */

import { masonryConfig } from "./masonryConfig";

/**
 * Listen for the button press and then save the link to DB and display it on the page
 */
export function initPostToMyLinks() {
    const postToMyLinksBtn = document.querySelector(masonryConfig.postToMyLinks.selector)
    // The button will not exist for a user that has already added this page to their links.
    if (!postToMyLinksBtn) { return; }

    postToMyLinksBtn.addEventListener("click", (event) => {
        event.preventDefault();
        postLink();
    });
}

/**
 * Save the settings to the DB
 */
async function postLink() {
    try {
        await fetch(masonryConfig.postToMyLinks.updateUrl, {
            method: "POST",
            body: getFormData(),
        });
    } catch (error) {
        console.warn(error);
    } finally {
        displayLinkOnPage();
    }
}

/**
 * To pass through the required FormData to save the page to my links
 *
 * @returns { FormData }
 */
function getFormData() {
    const formData = new FormData();
    formData.append("url", masonryConfig.postToMyLinks.url);
    formData.append("description", masonryConfig.postToMyLinks.heading);
    return formData;
}

/**
 * Add the link to the side panel
 */
function displayLinkOnPage() {
    const link = document.createElement("a");
    link.setAttribute("href", masonryConfig.postToMyLinks.url);
    link.innerText = masonryConfig.postToMyLinks.heading;

    const linkListItem = document.createElement("li");
    linkListItem.appendChild(link);

    const myLinksList = document.getElementById("side-menu-mylinks").querySelector("ul");
    myLinksList.appendChild(linkListItem);

    // Remove the postToMyLinks button
    const postToMyLinksBtn = document.querySelector(masonryConfig.postToMyLinks.selector)
    postToMyLinksBtn.parentNode.removeChild(postToMyLinksBtn);
}
