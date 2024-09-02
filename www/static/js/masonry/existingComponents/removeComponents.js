/**
 * To remove a component
 *
 */

import { gsap } from "gsap/all";
import { slideDown, slideUp, animateRotation } from "@js/util/animateUtils";
import { getMasonryConfig } from "../masonryConfig";
import { getComponentID } from "../masonryFunctions";
import { close as closeSettings, isOpen as isSettingsOpen } from "./updateComponentsSettings";
import UpdateMasonryEvents from "../updateMasonryEvents";

const ANIMATE_CLOSED_POSITION = 0;
const ANIMATE_OPEN_POSITION = 180;
const ANIMATE_ORIGIN = "20px 20px";
const ANIMATE_SPEED = 0.3;

const BUTTON_SELECTOR = '[data-target="delete"]';
const STATE_ATTRIBUTE = "data-state"; //can be 'opened' or 'closed'
const PANEL_SELECTOR = '[data-component-panel="delete"]';

/**
 * Toggle the remove palette and manage the confirm/deny actions
 */
export function initRemoveComponents() {
    document
        .querySelectorAll(getMasonryConfig().settings.existingComponentSelector)
        .forEach((component) => init(component)); //existing components
    document.addEventListener(UpdateMasonryEvents.COMPONENT_ADDED, (event) => init(event.detail.component)); //newly added components
    document.addEventListener(UpdateMasonryEvents.COMPONENT_UPDATED, (event) => init(event.detail.component)); //newly added components
}

/**
 * To show/hide the confirm/cancel remove component panel
 *
 * @param { Element }
 */
function init(component) {
    initToggleButton(component);
    initCancelButton(component);
    initConfirmButton(component);
}

/**
 * The show/hide button
 *
 * @param { Element }
 * @param { Element }
 */
function initToggleButton(component) {
    const toggleButtons = component.querySelectorAll(BUTTON_SELECTOR);
    toggleButtons.forEach((toggleButton) => {
        toggleButton.addEventListener("click", (event) => {
            event.preventDefault();
            isOpen(component) ? close(component) : open(component);
        });
    });
}

/**
 * Determine the state of the remove panel
 *
 * @param { Element }
 *
 * @returns { Boolean }
 */
export function isOpen(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    return toggleButton.getAttribute(STATE_ATTRIBUTE) === "opened";
}

/**
 * Open the settings panel
 *
 * @param { Element }
 */
function open(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, "opened");
    animateRotation(toggleButton, ANIMATE_OPEN_POSITION, ANIMATE_ORIGIN, ANIMATE_SPEED);
    const panel = component.querySelector(PANEL_SELECTOR);
    slideDown(panel, ANIMATE_SPEED);
    if (isSettingsOpen(component)) {
        closeSettings(component);
    }
}

/**
 * Close the settings panel
 *
 * @param { Element }
 */
export async function close(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, "closed");
    animateRotation(toggleButton, ANIMATE_CLOSED_POSITION, ANIMATE_ORIGIN, ANIMATE_SPEED);
    const panel = component.querySelector(PANEL_SELECTOR);
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Cancel the component deletion
 *
 * @param { Element }
 */
function initCancelButton(component) {
    const cancelDeleteButton = component.querySelector('[data-remove="0"]');
    cancelDeleteButton.addEventListener("click", (event) => {
        event.preventDefault();
        if (isOpen(component)) {
            close(component);
        }
    });
}

/**
 * Confirm the component deletion
 *
 * @param { Element }
 */
function initConfirmButton(component) {
    const confirmDeleteButton = component.querySelector('[data-remove="1"]');
    confirmDeleteButton.addEventListener("click", (event) => {
        event.preventDefault();
        removeComponent(component, confirmDeleteButton);
    });
}

/**
 * Save the removed component to the DB
 *
 * @param { Element }
 * @param { Element }
 */
async function removeComponent(component, confirmDeleteButton) {
    try {
        document.dispatchEvent(new Event(UpdateMasonryEvents.PROCESSING_START));
        confirmDeleteButton.classList.add("disabled");
        confirmDeleteButton.innerText = "Removing...";
        await fetch(getMasonryConfig().settings.updateUrl, {
            method: "POST",
            body: getFormData(component),
        });
        await slideUp(component);
    } catch (error) {
        console.warn(error);
        updateRemovedComponent(component);
        slideUp(component);
    } finally {
        component.remove();
        document.dispatchEvent(
            new CustomEvent(UpdateMasonryEvents.COMPONENT_REMOVED, {
                detail: {
                    component: component,
                },
            }),
        );
        document.dispatchEvent(new Event(UpdateMasonryEvents.PROCESSING_END));
    }
}

/**
 * To pass through the required FormData to remove the component in the DB
 *
 * @param { Element }
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
    formData.append("delete", getComponentID(component));
    formData.append("component", component.querySelector("[data-defn-id]").value);
    return formData;
}

/**
 * The component has been removed elsewhere, so notify the user
 * and suggest a page refresh for the latest data
 *
 * @param { Element }
 */
export function updateRemovedComponent(component) {
    component.querySelector("[data-component-handle]").remove(); //remove the drag trigger
    component.querySelector("[data-component-titlebar-container] > nav").remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement("div");
    errorElement.classList.add("alert", "alert-box");
    errorElement.innerHTML = `<b>Warning:</b> This component has been removed from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = component.querySelector("[data-collapsable] > div > section");
    if (insideComponentElement) {
        insideComponentElement.replaceChildren(errorElement);
    }

    gsap.from(errorElement, { opacity: 0, duration: ANIMATE_SPEED });
}
