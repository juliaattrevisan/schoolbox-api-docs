/**
 * Adds this page to my links
 *
 */

import { getMasonryConfig } from "./masonryConfig";

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
    const heading = getDecodedHeading();

    try {
        await fetch(getMasonryConfig().postToMyLinks.updateUrl, {
            method: "POST",
            body: getFormData(heading),
        });
    } catch (error) {
        console.warn(error);
    } finally {
        displayLinkOnPage(heading);
    }
}

/**
 * To pass through the required FormData to save the page to my links
 *
 * @returns { FormData }
 */
function getFormData(heading) {
    const formData = new FormData();
    formData.append("url", getMasonryConfig().postToMyLinks.url);
    formData.append("description", heading);
    return formData;
}

/**
 * Add the link to the side panel
 */
function displayLinkOnPage(heading) {
    const link = document.createElement("a");
    link.setAttribute("href", getMasonryConfig().postToMyLinks.url);
    link.innerText = heading;

    const linkListItem = document.createElement("li");
    linkListItem.appendChild(link);

    const myLinksList = document.getElementById("side-menu-mylinks").querySelector("ul");
    myLinksList.appendChild(linkListItem);

    // Remove the postToMyLinks button
    const postToMyLinksBtn = document.querySelector(getMasonryConfig().postToMyLinks.selector);
    postToMyLinksBtn.parentNode.removeChild(postToMyLinksBtn);
}

function getDecodedHeading() {
    const textarea = document.createElement("textarea");
    let heading = getMasonryConfig().postToMyLinks.heading;
    let decodedHeading;

    do {
        decodedHeading = heading;
        textarea.innerHTML = decodedHeading;
        heading = textarea.value;
    } while (heading !== decodedHeading);

    return heading;
}
