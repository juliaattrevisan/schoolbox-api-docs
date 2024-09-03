/**
 * Save the new sorting order, as a component has been moved to a new spot or
 * a new component has been added to the page
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import { getComponentID } from "../masonryFunctions";
import { updateRemovedComponent } from "./removeComponents";

/**
 * Submit the new component order to the DB
 *
 * @param { Element }
 */
export async function saveComponentOrder(component) {
    try {
        const response = await fetch(getMasonryConfig().settings.updateUrl, {
            method: "POST",
            body: getFormData(component),
        });
        const responseData = await response.json();
        if (responseData.hasComponentBeenDeleted) {
            updateRemovedComponent(component);
        }
    } catch (error) {
        console.warn(error);
    }
}

/**
 * To pass through the required FormData to save the new position in the DB
 *
 * @params { Element } the component that's been moved
 *
 * @returns { FormData }
 */
function getFormData(component) {
    const formData = new FormData();
    formData.append("column1", getContainerComponentIDs(document.querySelector('[data-position="left"]'))); //left side panel
    formData.append("column2", getContainerComponentIDs(document.querySelector('[data-position="right"]'))); //right side panel
    formData.append("column3", getContainerComponentIDs(document.querySelector('[data-position="top"]'))); //top panel... I know right?!
    formData.append("movedComponentId", getComponentID(component));
    formData.append("type", "components");
    formData.append(
        getMasonryConfig().settings.formData.pageInfo.type,
        getMasonryConfig().settings.formData.pageInfo.id,
    );

    // Admin templates require a context of the selected page type to be passed through, so we need to check if it's set
    if (getMasonryConfig().settings.formData.pageInfo.context) {
        formData.append("context", getMasonryConfig().settings.formData.pageInfo.context);
    }
    return formData;
}

/**
 * To retrieve all component ids within a container
 *
 * @param { Element }
 *
 * @returns { String }
 */
function getContainerComponentIDs(componentContainer) {
    let containerComponentIDList = []; //add all ids into an array
    componentContainer.querySelectorAll(getMasonryConfig().settings.existingComponentSelector).forEach((sortable) => {
        if (sortable.id && !sortable.dataset.placeHolderComponent) {
            //the <div class="masonry-placeholder" /> exists in each parent container, so we don't want that
            containerComponentIDList.push(getComponentID(sortable));
        }
    });
    return containerComponentIDList.join(","); //return just a string or the component ids with a comma in between each
}
