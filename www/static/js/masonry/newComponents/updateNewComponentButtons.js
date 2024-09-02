/**
 * The buttons that sit in the new component tabs
 * To update the component counts in the new components tabs when added / removed
 * For example Text Box (0) becomes Text Box (1)
 * And also disable/enable if the component is allowed only one instance on the page
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import UpdateMasonryEvents from "../updateMasonryEvents";

/**
 * Start listening for the relevant events
 */
export function initUpdateNewComponentButtons() {
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * To increment the relevant counter and check if need to disable
 */
function initOnComponentAddedListener() {
    document.addEventListener(UpdateMasonryEvents.COMPONENT_ADDED, (event) => {
        const newComponentCount = updateComponentCount(event.detail.paletteElement, true);
        checkButtonStatus(event.detail.paletteElement, newComponentCount);
    });
}

/**
 * To decrement the relevant counter and check if need to re-enable
 */
function initOnComponentRemovedListener() {
    document.addEventListener(UpdateMasonryEvents.COMPONENT_REMOVED, (event) => {
        const targetId = event.detail.component.dataset.componentDefinitionId;
        const counter = document.querySelector(
            `[data-component-palette] [${getMasonryConfig().settings.formData.newComponentAddAttribute}="${targetId}"]`,
        );
        const paletteElement = counter.parentElement;

        const newComponentCount = updateComponentCount(paletteElement, false);
        checkButtonStatus(paletteElement, newComponentCount);
    });
}

/**
 * Update the counter element with the correct number of component type on the page
 *
 * @param { Element }
 * @param { Boolean }
 *
 * @returns { Number }
 */
function updateComponentCount(paletteElement, isIncrement) {
    const counterElement = paletteElement.querySelector('[data-relation="count"]');
    if (!counterElement) {
        return isIncrement; // No counter element, so nothing to update
    }
    const currentComponentCount = parseInt(counterElement.textContent);
    const currentCount = isIncrement ? currentComponentCount + 1 : currentComponentCount - 1;
    counterElement.textContent = currentCount;
    return currentCount;
}

/**
 * Some components can only have one instance, so we have to disable further instances
 * of the component being created once one has been added
 *
 * @param { Number }
 */
function checkButtonStatus(paletteElement, componentCount) {
    if (componentCount === 0) {
        paletteElement.classList.remove("disabled");
        paletteElement.style.opacity = 1;
    } else if (paletteElement.getAttribute("data-multi") === "one-only") {
        paletteElement.classList.add("disabled");
    } else {
        paletteElement.style.opacity = 1;
    }
}

/**
 * Updates all the component counts based on what can be found on the page
 * Note: This will not work as intended if multiple components with the same definition ID are in the pallette, e.g. on eportfolios
 */
export function updateAllComponentCounts() {
    document.querySelectorAll("[data-component-palette]").forEach((paletteElement) => {
        const componentDefinitionId = paletteElement.querySelector("[data-component-definition-id]").dataset
            .componentDefinitionId;
        const componentCount = document.querySelectorAll(
            `[data-component][data-component-definition-id="${componentDefinitionId}"]`,
        ).length;
        const counterElement = paletteElement.querySelector('[data-relation="count"]');
        if (counterElement) {
            counterElement.textContent = componentCount;
        }
        checkButtonStatus(paletteElement, componentCount);
    });
}
