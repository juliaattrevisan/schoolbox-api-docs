/**
 * Adds this page to my links
 *
 */

import { unescape } from "es-toolkit";
import { getMasonryConfig } from "./masonryConfig";

/**
 * Get the decoded page heading to use for the saved link and side menu display.
 *
 * @returns { string }
 */
function getHeading() {
    return unescape(getMasonryConfig().postToMyLinks.heading);
}

/**
 * Listen for the button press and then save the link to DB and display it on the page
 */
export function initPostToMyLinks() {
    const postToMyLinksBtn = document.querySelector(getMasonryConfig().postToMyLinks.selector);
    // The button will not exist for a user that has already added this page to their links.
    if (!postToMyLinksBtn) {
        return;
    }

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
        await fetch(getMasonryConfig().postToMyLinks.updateUrl, {
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
    formData.append("url", getMasonryConfig().postToMyLinks.url);
    formData.append("description", getHeading());
    return formData;
}

/**
 * Add the link to the side panel
 */
function displayLinkOnPage() {
    const link = document.createElement("a");
    link.setAttribute("href", getMasonryConfig().postToMyLinks.url);
    link.innerText = getHeading();

    const linkListItem = document.createElement("li");
    linkListItem.appendChild(link);

    const myLinksList = document.getElementById("side-menu-mylinks").querySelector("ul");
    myLinksList.appendChild(linkListItem);

    // Remove the postToMyLinks button
    const postToMyLinksBtn = document.querySelector(getMasonryConfig().postToMyLinks.selector);
    postToMyLinksBtn.parentNode.removeChild(postToMyLinksBtn);
}
