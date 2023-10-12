/**
 *  To toggle the display of the content when the button is selected
 *  floating-ui/dom is used to help position the content relative to the button,
 *  trying to ensure the content is always in the viewport
 */

import {
    ReferenceElement,
    ComputePositionReturn,
    computePosition,
    autoUpdate,
    flip,
    shift,
    Placement,
    Boundary,
} from "@floating-ui/dom";
import gsap from "gsap";
import type { Ref } from "vue";
import { onClickOutside } from "@vueuse/core";

/**
 * To toggle the display of the content when the button is selected
 * @param {Ref<HTMLButtonElement>} button - The pop out button
 * @param {string} buttonLabel - The label to display on the button
 * @param {string} contentId - The id of the pop out content
 * @param {string} optimalPlacement - The optimal placement of the pop out content
 * @param {boolean} isFullWidth - Whether the pop out is a filter or not
 * @returns {object} - The isOpen ref
 */
export function usePopOut(
    button: Ref<HTMLButtonElement>,
    buttonLabel: string,
    contentId: string,
    optimalPlacement = "bottom-end",
    isFullWidth = false
) {
    const isOpen = window.Vue.ref(false); // Whether the button is open or not
    let content: HTMLElement; // The content to display when the button is selected
    let cleanup: () => void; // The cleanup function returned by @floating-ui/dom

    /**
     * The label to display on the button,
     * changes depending on whether the pop out is open or not
     */
    const currentLabel = window.Vue.computed(() => {
        return isOpen.value ? `Hide ${buttonLabel}` : `Show ${buttonLabel}`;
    });

    /**
     * Close the content if it's open
     */
    function close() {
        if (isOpen.value) {
            isOpen.value = false;
            content.style.display = "none";
            content.setAttribute("aria-hidden", "true");
            // reset the colour of the button to default after changing on open state
            button.value.style.backgroundColor = "";
            cleanup();
        }
    }

    /**
     * To get the transform origin of the content, relative to where it's positioned
     * @param {Placement} placement - The placement of the pop out content
     * @returns {string} - The transform origin
     */
    function getTransformOrigin(placement: Placement) {
        if (placement === "top-start") {
            return "bottom left";
        }
        if (placement === "top-end") {
            return "bottom right";
        }
        if (placement === "bottom-start") {
            return "top left";
        }
        if (placement === "bottom-end") {
            return "top right";
        }
        return "top right";
    }

    /**
     * To animate the opening of the content, relative to where it's positioned
     * @param {ComputePositionReturn} position - The position of the content
     */
    function animateOpen(position: ComputePositionReturn) {
        // dont animate if the user prefers reduced motion
        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches ?? false;
        gsap.from(content, {
            opacity: 0,
            scaleX: 0.95,
            scaleY: 0.95,
            transformOrigin: isFullWidth ? "top middle" : getTransformOrigin(position.placement),
            duration: reduceMotion ? 0 : 0.3,
        });
    }

    /**
     * To compute the position of the content relative to the button
     * @returns {Promise<ComputePositionReturn>} - The position of the content
     */
    async function getPosition() {
        const position = await computePosition(button.value as ReferenceElement, content, {
            placement: optimalPlacement as Placement,
            middleware: [
                flip({
                    boundary: document.querySelector("#container") as Boundary,
                }),
                shift({
                    boundary: document.querySelector("#container") as Boundary,
                }),
            ],
        });
        return position;
    }

    /**
     * To update the position of the content when the button
     * is selected or there's a layout shift
     */
    async function updatePosition() {
        const position = await getPosition();
        content.style.left = `${position.x}px`;
        content.style.top = `${position.y}px`;
    }

    /**
     * Open the content using @floating-ui/dom to position it relative to the button
     * https://floating-ui.com/
     */
    async function open() {
        isOpen.value = true;
        content.style.display = "block"; // The element must be visible to correctly compute its position
        content.setAttribute("aria-hidden", "false");
        // the button will always match the colour of the content when open
        button.value.style.backgroundColor = window.getComputedStyle(content).getPropertyValue("background-color");
        cleanup = autoUpdate(button.value as ReferenceElement, content, updatePosition as () => void);
        const position = await getPosition();
        animateOpen(position);
    }

    /**
     * When the button is clicked, close the content if it's open,
     * otherwise open it using @floating-ui/dom to position it relative to the trigger
     * while also trying to keep it in the viewport
     */
    async function onButtonSelected() {
        if (!content) {
            return;
        }
        if (isOpen.value) {
            close();
            return;
        }
        await open();
    }

    /**
     * Get the content, the element that's displayed when the button is selected
     */
    function getContent() {
        content = document.querySelector(`#${contentId}[data-dropdown-content]`) as HTMLElement;
        if (content) {
            content.setAttribute("aria-hidden", "true");
            // to close the content when the user clicks outside of it
            onClickOutside(button, close, { ignore: [`#${content.id}[data-dropdown-content]`] });
        }
    }

    /**
     * Initialise the display toggle of the content when the button is selected
     */
    window.Vue.onMounted(() => {
        getContent();
        // if it's not already in the DOM, wait for the DOM to be ready to try again
        if (!content) {
            window.addEventListener("DOMContentLoaded", () => {
                getContent();
            });
        }
    });

    return { currentLabel, isOpen, onButtonSelected };
}
