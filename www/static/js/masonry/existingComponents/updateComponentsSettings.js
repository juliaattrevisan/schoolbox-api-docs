/**
 * Control the component settings panel and ensure it gets updated via ajax
 *
 */

import { gsap } from "gsap/all";
import { slideDown, slideUp, animateRotation } from "@js/util/animateUtils";
import { getLoadedHTML } from "@js/util/domUtils";
import { getMasonryConfig } from "../masonryConfig";
import { close as closeRemove, isOpen as isRemoveOpen } from "./removeComponents";
import UpdateMasonryEvents from "../updateMasonryEvents";
import { updateRemovedComponent } from "./removeComponents";

const ANIMATE_CLOSED_POSITION = 0;
const ANIMATE_OPEN_POSITION = 90;
const ANIMATE_ORIGIN = "20px 20px";
const ANIMATE_SPEED = 0.3;

const BUTTON_SELECTOR = '[data-target="settings"]';
const PANEL_SELECTOR = '[data-component-panel="settings"]';
const STATE_ATTRIBUTE = "data-state"; //can be 'opened' or 'closed'
const QUERY_STRING_ATTRIBUTE = "data-query-string"; //to store the current settings to be sent to the DB on update

/**
 * Initiate the settings toggle and form for each component and when a
 * new component is added or updated
 */
export function initUpdateComponentsSettings() {
    document
        .querySelectorAll(getMasonryConfig().settings.existingComponentSelector)
        .forEach((component) => init(component));
    document.addEventListener(UpdateMasonryEvents.COMPONENT_ADDED, (event) => init(event.detail.component));
    document.addEventListener(UpdateMasonryEvents.COMPONENT_UPDATED, (event) => init(event.detail.component));
}

/**
 * Set up the settings buttons and what happens on form submission
 *
 * @param { Element }
 */
function init(component) {
    initToggleButtons(component);
    initForm(component);
}

/**
 * To show/hide the settings panel with the toggle button.
 * There's also sometimes a settings toggle button in the empty component state as well,
 * so check for that
 *
 * @param { Element }
 */
function initToggleButtons(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    if (!toggleButton) return;

    toggleButton.addEventListener("click", (event) => {
        event.preventDefault();
        isOpen(component) ? close(component) : open(component);
    });
    const emptyStateToggleButtons = component.querySelectorAll(`${BUTTON_SELECTOR}.empty-edit`);
    emptyStateToggleButtons.forEach((emptyStateToggleButton) => {
        emptyStateToggleButton.addEventListener("click", (event) => {
            event.preventDefault();
            isOpen(component) ? close(component) : open(component);
        });
    });
}

/**
 * Determine the state of the settings panel
 *
 * @param { Element }
 *
 * @returns { Boolean }
 */
export function isOpen(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    if (!toggleButton) return;
    return toggleButton.getAttribute(STATE_ATTRIBUTE) === "opened";
}

/**
 * Open the settings panel
 *
 * @param { Element }
 */
function open(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    if (!toggleButton) return;
    toggleButton.setAttribute(STATE_ATTRIBUTE, "opened");
    toggleButton.setAttribute(QUERY_STRING_ATTRIBUTE, getQueryString(component));
    animateRotation(toggleButton, ANIMATE_OPEN_POSITION, ANIMATE_ORIGIN, ANIMATE_SPEED);
    const panel = component.querySelector(PANEL_SELECTOR);
    slideDown(panel, ANIMATE_SPEED);
    if (isRemoveOpen(component)) {
        closeRemove(component);
    }
}

/**
 * To be able to compare the form query string on form submit, as we don't want to submit if nothing has changed
 *
 * @param { Element }
 *
 * @returns { String }
 */
function getQueryString(component) {
    const panelForm = component.querySelector(PANEL_SELECTOR).querySelector("form");
    return new URLSearchParams(new FormData(panelForm)).toString();
}

/**
 * Close the settings panel
 *
 * @param { Element }
 */
export async function close(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    if (!toggleButton) return;
    toggleButton.setAttribute(STATE_ATTRIBUTE, "closed");
    animateRotation(toggleButton, ANIMATE_CLOSED_POSITION, ANIMATE_ORIGIN, ANIMATE_SPEED);
    const panel = component.querySelector(PANEL_SELECTOR);
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Ensure updates to the settings panel are updated via AJAX
 *
 * @param { Element }
 */
function initForm(component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    if (!toggleButton) return;
    const panelForm = component.querySelector(PANEL_SELECTOR).querySelector("form");
    panelForm.addEventListener("submit", (event) => {
        event.preventDefault();
        toggleButton.getAttribute(QUERY_STRING_ATTRIBUTE) === getQueryString(component)
            ? close(component)
            : saveComponentSettings(component, panelForm);
    });
}

/**
 * Save the settings to the DB
 *
 * @param { Element }
 * @param { HTMLFormElement }
 */
async function saveComponentSettings(component, form) {
    const submitFormButton = form.querySelector('input[type="submit"]');
    try {
        document.dispatchEvent(new Event(UpdateMasonryEvents.PROCESSING_START));
        submitFormButton.disabled = true;
        submitFormButton.value = "Updating...";

        // Set X-Requested-With header to allow for AJAX specific processing in the backend controller
        const response = await fetch(
            getMasonryConfig().settings.updateUrl,
            {
                method: "POST",
                body: getSaveComponentSettingsParams(form),
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            }
        );
        const responseData = await response.text();
        await updateComponent(component, responseData);
    } catch (error) {
        console.warn(error);
        updateRemovedComponent(component); //currently the assumed error, that the component no longer exists
        slideUp(component.querySelector(PANEL_SELECTOR), ANIMATE_SPEED);
    } finally {
        document.dispatchEvent(new Event(UpdateMasonryEvents.PROCESSING_END));
    }
}

/**
 * To pass through the required FormData to update the component in the DB
 *
 * @param { Element }
 *
 * @returns { Object }
 */
function getSaveComponentSettingsParams(form) {
    const formData = new FormData(form);
    // Replace cid parameter if it is empty or not set and getMasonryConfig().pageInfo.type === "cid"
    if (
        getMasonryConfig().settings.formData.pageInfo.type === "cid" &&
        (!formData.has("cid") || formData.get("cid") === "")
    ) {
        formData.append("cid", encodeURIComponent(getMasonryConfig().settings.formData.pageInfo.id));
    }
    // Admin templates require a context of the selected page type to be passed through, so we need to check if it's set
    const context = getMasonryConfig().settings.formData.pageInfo.context;
    if (context) {
        formData.append("context", encodeURIComponent(context));
    }
    return formData;
}

/**
 * Now we have the returned response from the settings save, replace the old component with the new component
 *
 * @param { Element }
 * @param { String }
 */
async function updateComponent(component, responseData) {
    const updatedComponent = getUpdatedComponent(component, responseData);
    const newComponent = await getLoadedHTML(updatedComponent.outerHTML); //ensure the new html is properly loaded
    component.replaceWith(newComponent); //switch over to the newly updated component
    gsap.to(newComponent, {
        //fade in the updated component
        duration: ANIMATE_SPEED,
        opacity: 1,
        startAt: { opacity: 0 },
    });
    document.dispatchEvent(
        new CustomEvent(UpdateMasonryEvents.COMPONENT_UPDATED, {
            detail: { component: newComponent },
        })
    );
}

/**
 * To get the updated component as an Element once returned as it's
 * currently in the form of a String
 *
 * @param { Element }
 * @param { String }
 *
 * @returns { Element }
 */
function getUpdatedComponent(component, documentString) {
    const parser = new DOMParser();
    const document = parser.parseFromString(documentString, "text/html");
    return document.documentElement.querySelector(`#${component.id}`);
}
