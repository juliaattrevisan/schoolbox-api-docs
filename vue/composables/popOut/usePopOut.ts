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
    FloatingElement,
} from "@floating-ui/dom";
import gsap from "gsap";
import type { Ref } from "vue";
import { onClickOutside } from "@vueuse/core";

/**
 * To toggle the display of the content when the button is selected
 * @param {Ref<HTMLButtonElement | HTMLElement | null>} button - The pop out button, if a HTMLButtonElement is used it's styles will be changed
 * @param {string | Ref<HTMLElement | null>} contentId - The id of the pop out content, or a ref to the pop out content
 * @param {string} buttonLabel - The label to display on the button
 * @param {Placement} optimalPlacement - The optimal placement of the pop out content
 * @param {boolean} isFullWidth - whether to display the pop out content has full width
 * @param {boolean} isCloseOnContentClick - whether to close the pop out content when it's clicked
 * @param {HTMLElement | string | null} moveTo - An optional element to move the pop out content to
 * @param {() => void} onClose - An optional callback to run when the pop out is closed
 * @returns {object} - The isOpen ref
 */
export function usePopOut(
    button: Ref<HTMLButtonElement | HTMLElement | null>,
    contentId: string | Ref<HTMLElement | null>,
    buttonLabel: string,
    optimalPlacement: Placement = "bottom-end",
    isFullWidth: boolean = false,
    isCloseOnContentClick: boolean = false,
    moveTo: HTMLElement | string | null = null,
    onClose: () => void = () => {},
) {
    const isOpen = window.Vue.ref(false); // Whether the button is open or not
    const contentInitParent = window.Vue.ref<HTMLElement | null>(null); // The initial parent of the content, if it was moved
    const content = window.Vue.ref<HTMLElement | null>(null); // The content to display when the button is selected
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

            if (content.value !== null) {
                content.value.style.display = "none";
                content.value.setAttribute("aria-hidden", "true");
            }

            // reset the colour of the button to default after changing on open state
            if (button.value instanceof HTMLButtonElement) {
                button.value.style.backgroundColor = "";
            }

            cleanup();
            onClose();
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
        if (content.value === null) throw new Error("Unable to animate open - content element not found");

        // don't animate if the user prefers reduced motion
        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches ?? false;
        gsap.killTweensOf(content.value);
        gsap.set(content.value, {
            opacity: 0,
            scaleX: 0.95,
            scaleY: 0.95,
        });
        gsap.to(content.value, {
            opacity: 1,
            scaleX: 1,
            scaleY: 1,
            transformOrigin: getTransformOrigin(position.placement),
            duration: reduceMotion ? 0 : 0.3,
        });
    }

    /**
     * To compute the position of the content relative to the button
     * @param {ReferenceElement} buttonElement - The button element
     * @param {FloatingElement} contentElement - The content element
     * @returns {Promise<ComputePositionReturn>} - The position of the content
     */
    async function getPosition(
        buttonElement: ReferenceElement,
        contentElement: FloatingElement,
    ): Promise<ComputePositionReturn> {
        const position = await computePosition(buttonElement, contentElement, {
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
        const buttonElement = button.value;
        const contentElement = content.value;
        if (buttonElement === null || contentElement === null) return;

        const position = await getPosition(buttonElement, contentElement);
        contentElement.style.left = `${position.x}px`;
        contentElement.style.top = `${position.y}px`;
    }

    /**
     * Open the content using @floating-ui/dom to position it relative to the button
     * https://floating-ui.com/
     */
    async function open() {
        const buttonElement = button.value;
        const contentElement = content.value;
        if (buttonElement === null) throw new Error("Unable to open content - button element not found");
        if (contentElement === null) throw new Error("Unable to open content - content element not found");

        isOpen.value = true;
        contentElement.style.display = "block"; // The element must be visible to correctly compute its position
        contentElement.setAttribute("aria-hidden", "false");
        // the button will always match the colour of the content when open
        if (buttonElement instanceof HTMLButtonElement) {
            buttonElement.style.backgroundColor = window
                .getComputedStyle(contentElement)
                .getPropertyValue("background-color");
        }
        cleanup = autoUpdate(buttonElement, contentElement, () => {
            void updatePosition();
        });
        if (isFullWidth && buttonElement.offsetWidth > 500) {
            // make the content the same width of the button
            contentElement.style.width = `${buttonElement.offsetWidth}px`;
        }
        const position = await getPosition(buttonElement, contentElement);
        animateOpen(position);
    }

    /**
     * When the button is clicked, close the content if it's open,
     * otherwise open it using @floating-ui/dom to position it relative to the trigger
     * while also trying to keep it in the viewport
     */
    async function onButtonSelected() {
        if (content.value === null) throw new Error("Unable to open content - content element not found");
        if (isOpen.value) {
            close();
            return;
        }
        await open();
    }

    /**
     * Move the content to a different parent, if requested, and keep track of the initial parent.
     *
     * For scenarios where the location of the content element is not ideal and could lead to issues such as being
     * clipped by an overflow:hidden parent, but placing it there initially is not possible or not practical
     * e.g. if the pop-out is in a iterated-over subcomponent
     */
    function moveContentToNewParent() {
        if (content.value === null || !moveTo) {
            return;
        }

        const moveToEl = typeof moveTo === "string" ? document.querySelector(moveTo) : moveTo;
        if (moveToEl) {
            // Store the original position of the content
            contentInitParent.value = content.value.parentElement;
            moveToEl.appendChild(content.value);
        }
    }

    /**
     * Returns the content back to its original parent, if it was moved
     */
    function restoreContentParent() {
        if (contentInitParent.value !== null && content.value !== null) {
            contentInitParent.value.appendChild(content.value);
        }
    }

    /**
     * Find the content element based on the value of contentId
     * @returns {HTMLElement | null} - The content element, or null if it's not found
     */
    function findContentElement(): HTMLElement | null {
        if (typeof contentId === "string") {
            return document.querySelector(`#${contentId}[data-dropdown-content]`);
        }

        return contentId.value instanceof HTMLElement ? contentId.value : null;
    }

    /**
     * Get the content, the element that's displayed when the button is selected
     */
    async function getContent() {
        let newContent = findContentElement();

        // If the content isn't found, and we are looking based on an ID, try waiting.
        // This could occur if, for example, the component is rendered only after a user action
        if (typeof contentId === "string" && newContent === null) {
            // Make sure the DOM is ready before trying to find the content
            if (document.readyState !== "complete") {
                await new Promise<void>((resolve) => {
                    window.addEventListener("DOMContentLoaded", () => {
                        resolve();
                    });
                });
            }

            // Wait for the next tick to try again
            await window.Vue.nextTick();
            newContent = findContentElement();
        }

        if (newContent !== null) {
            // Has the content changed? If so, restore the old one first if needed, then update
            if (content.value !== newContent) {
                close();
                restoreContentParent();
            }

            content.value = newContent;
            content.value.setAttribute("aria-hidden", "true");

            if (moveTo) {
                moveContentToNewParent();
            }

            // to close the content when the user clicks outside of it
            onClickOutside(button, close, {
                ignore: isCloseOnContentClick ? [] : [content],
            });
        }
    }

    /**
     * Initialise the display toggle of the content when the button is selected
     * If contentId is a ref, watch it for changes
     */
    window.Vue.onMounted(async () => {
        await getContent();

        if (typeof contentId !== "string") {
            window.Vue.watch(contentId, getContent, { immediate: false });
        }
    });

    /**
     * Before the component is unmounted, close the content if it's open
     */
    window.Vue.onBeforeUnmount(() => {
        close();
        restoreContentParent();
    });

    return { currentLabel, isOpen, onButtonSelected, close };
}
