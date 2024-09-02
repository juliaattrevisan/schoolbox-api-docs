/**
 * To enable/disable the editing of components by showing the drag, edit and remove
 * buttons on each component header
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import UpdateMasonryEvents from "../updateMasonryEvents";

/**
 * Listen to changes and enable/disable the sorting based on the status of #component-lock-switch
 */
export function initToggleLockComponents() {
    const componentLockSwitch = document.querySelector(getMasonryConfig().toggleLockComponents.selector);
    componentLockSwitch.addEventListener("change", () => {
        document
            .querySelectorAll(getMasonryConfig().settings.existingComponentSelector)
            .forEach((component) => updateComponentDisplay(component));
    });
    document.addEventListener(UpdateMasonryEvents.COMPONENT_ADDED, (event) =>
        updateComponentDisplay(event.detail.component),
    );
    document.addEventListener(UpdateMasonryEvents.COMPONENT_UPDATED, (event) =>
        updateComponentDisplay(event.detail.component),
    );

    // Set the initial state of the toggle, in case the page is loaded with the toggle already checked
    updateComponentDisplay(document);
}

/**
 * Show or hide the relevant component editing elements
 *
 * @param { Element }
 */
function updateComponentDisplay(component) {
    component.querySelectorAll("a[data-component-settings], [data-component-handle]").forEach((element) => {
        isToggleUnlocked() ? (element.style.display = "inline-block") : (element.style.display = "none");
    });
}

/**
 * To determine whether the toggle is locked,
 * is also checked by new components to determine its initial display
 *
 * @returns { Boolean }
 */
function isToggleUnlocked() {
    return document.getElementById("component-lock-switch").querySelector("input").checked;
}
