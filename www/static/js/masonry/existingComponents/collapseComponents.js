/**
 * Manage a component's ability to open and close
 *
 */

import { slideDown, slideUp, animateRotation } from "@js/util/animateUtils";
import { getMasonryConfig } from "../masonryConfig";
import UpdateMasonryEvents from "../updateMasonryEvents";

const ANIMATE_CLOSED_POSITION = 180;
const ANIMATE_OPEN_POSITION = 0;
const ANIMATE_ORIGIN = "20px 20px";
const ANIMATE_SPEED = 0.4;

const PANEL_SELECTOR = '[data-collapsable="true"]';
const BUTTON_SELECTOR = '[data-collapser="true"]';

/**
 * Set up all components and listen for newly added ones
 */
export function initCollapseComponents() {
    document
        .querySelectorAll(getMasonryConfig().settings.existingComponentSelector)
        .forEach((component) => init(component));
    document.addEventListener(UpdateMasonryEvents.COMPONENT_ADDED, (event) => init(event.detail.component));
    document.addEventListener(UpdateMasonryEvents.COMPONENT_UPDATED, (event) => init(event.detail.component));
}

/**
 * Open and close a component
 *
 * @param component { Element }
 */
function init(component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    if (collapseButton) {
        //not all components have a collapse button to access
        collapseButton.addEventListener("click", (event) => {
            event.preventDefault();
            isOpen(component) ? close(component) : open(component);
        });
    }
}

/**
 * Check to determine components collapse state
 *
 * @params { Element }
 *
 * @returns { Boolean }
 */
export function isOpen(component) {
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    if (!collapsePanel) {
        return false;
    }
    return collapsePanel.getAttribute("data-collapse-state") === "uncollapsed";
}

/**
 * Uncollapse a component, sliding the content within down
 *
 * @params { Element }
 */
export async function open(component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    animateRotation(
        collapseButton,
        collapseButton.classList.contains("icon-up-arrow") ? ANIMATE_OPEN_POSITION : ANIMATE_CLOSED_POSITION,
        ANIMATE_ORIGIN,
        ANIMATE_SPEED,
    );
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    collapsePanel.setAttribute("data-collapse-state", "uncollapsed");
    collapsePanel.dispatchEvent(new CustomEvent("expandStart.collapsable"));
    await slideDown(collapsePanel, ANIMATE_SPEED, "expandStarted.collapsable");
    collapsePanel.dispatchEvent(new CustomEvent("expandStop.collapsable"));
}

/**
 * Collapse a component, sliding the content within up
 *
 * @params { Element }
 */
export async function close(component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    animateRotation(
        collapseButton,
        collapseButton.classList.contains("icon-up-arrow") ? ANIMATE_CLOSED_POSITION : ANIMATE_OPEN_POSITION,
        ANIMATE_ORIGIN,
        ANIMATE_SPEED,
    );
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    collapsePanel.setAttribute("data-collapse-state", "collapsed");
    collapsePanel.dispatchEvent(new CustomEvent("collapseStart.collapsable"));
    await slideUp(collapsePanel, ANIMATE_SPEED);
    collapsePanel.dispatchEvent(new CustomEvent("collapseStop.collapsable"));
}

/**
 * Check the state of the 'Collapse Content' checkbox to determine display
 *
 * @params { Element }
 *
 * @returns { Boolean }
 */
export function isCollapseComponentSelected(component) {
    const collapseCheckbox = component.querySelector(`#${component.id}-collapse`);
    if (!collapseCheckbox) {
        return false;
    }
    return collapseCheckbox.getAttribute("checked") === "checked" ? true : false;
}
