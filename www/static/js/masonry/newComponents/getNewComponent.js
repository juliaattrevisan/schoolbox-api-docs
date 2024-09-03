/**
 * Save the new component to the DB and then add into the display
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import { getLoadedHTML } from "@js/util/domUtils";

/**
 * Submit the new component to the DB and add to display
 */
export async function getNewComponent(component) {
    try {
        const response = await fetch(getMasonryConfig().settings.updateUrl, {
            method: "POST",
            body: getFormData(component),
        });
        const newComponentText = await response.text(); //it's currently a String
        const parser = new DOMParser(); //so we need to create an Element from it
        const document = parser.parseFromString(newComponentText, "text/html"); //by parsing it into HTML
        const newComponentHTML = document.querySelector("div"); //and then we can get the first div, which will be the entire component
        const newComponent = replaceExistingComponent(component, newComponentHTML);
        return newComponent;
    } catch (error) {
        console.warn(error);
    }
}

/**
 * Update the just added component to the actual component that's been loaded in
 *
 * @params { Element } the component that's been updated
 * @params { Element } the component's new markup
 *
 * @returns { Element }
 */
async function replaceExistingComponent(component, newComponentHTML) {
    const newComponent = await getLoadedHTML(newComponentHTML.outerHTML); //enure the new html is properly loaded
    component.replaceWith(newComponent); //switch over to the newly created component
    return newComponent;
}

/**
 * To pass through the required FormData to save the new component
 *
 * @params { Element } the component that's been moved
 *
 * @returns { FormData }
 */
function getFormData(component) {
    const formData = new FormData();
    formData.append(
        getMasonryConfig().settings.formData.pageInfo.type,
        getMasonryConfig().settings.formData.pageInfo.id,
    );

    // Admin templates require a context of the selected page type to be passed through, so we need to check if it's set
    if (getMasonryConfig().settings.formData.pageInfo.context) {
        formData.append("context", getMasonryConfig().settings.formData.pageInfo.context);
    }

    //this is to retrieve the new component id to know which type of component to create
    formData.append(
        "add",
        component.querySelector("a").getAttribute(getMasonryConfig().settings.formData.newComponentAddAttribute),
    );

    // EPortfolios require a "from" field to be set
    if (getMasonryConfig().settings.formData.newComponentFromAttribute) {
        formData.append(
            "from",
            component.querySelector(getMasonryConfig().settings.formData.newComponentFromAttribute),
        );
    }
    return formData;
}
