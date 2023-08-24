/**
 *
 */

import { ReferenceElement, ComputePositionReturn, computePosition, flip, shift, Placement } from "@floating-ui/dom";
import gsap from "gsap";
import type { Ref } from "vue";

/**
 * To toggle the display of the content when the button is selected
 * @param {Ref<HTMLButtonElement>} button - The dropdown button
 * @param {string} buttonLabel - The label to display on the button
 * @param {string} contentId - The id of the dropdown content
 * @returns {object} - The isOpen ref
 */
export function useDropdown(button: Ref<HTMLButtonElement>, buttonLabel: string, contentId: string) {
    const contentIdAttribute = "content-id"; // The attribute used to identify the dropdown content
    const isOpen = window.Vue.ref(false); // Whether the dropdown is open or not
    let content: HTMLElement; // The dropdown content, the element that's displayed when the button is selected

    /**
     * The label to display on the button,
     * changes depending on whether the dropdown is open or not
     */
    const currentLabel = window.Vue.computed(() => {
        return isOpen.value ? `Close ${buttonLabel}` : `Open ${buttonLabel}`;
    });

    /**
     * Close the dropdown if it's open
     */
    function close() {
        if (isOpen.value) {
            isOpen.value = false;
            content.style.display = "none";
            content.setAttribute("aria-hidden", "true");
        }
    }

    /**
     * When the document is clicked, close the dropdown if it's open
     * @param {MouseEvent} event - The event that triggered the close
     */
    function onDocumentClick(event?: MouseEvent) {
        // If the clicked target is inside the dropdown content and aria-autoclose is "false", do nothing
        if (
            event &&
            event.target &&
            content.contains(event.target as Node) &&
            content.getAttribute("aria-autoclose") === "false"
        ) {
            return;
        }
        // If the clicked target is the dropdown trigger, also do nothing
        if (
            event &&
            event.target &&
            (event.target as HTMLElement).hasAttribute(contentIdAttribute) &&
            (event.target as HTMLElement).getAttribute(contentIdAttribute) === contentId
        ) {
            return;
        }
        close();
        document.removeEventListener("click", onDocumentClick);
    }

    /**
     * To get the transform origin of the dropdown content, relative to where it's positioned
     * @param {Placement} placement - The placement of the dropdown content
     * @returns {string} - The transform origin
     */
    function getTransformOrigin(placement: Placement) {
        if (placement === "bottom-end") {
            return "top right";
        }
        if (placement === "top-end") {
            return "bottom right";
        }
        if (placement === "bottom-start") {
            return "top left";
        }
        if (placement === "top-start") {
            return "top right";
        }
        return "top right";
    }

    /**
     * To animate the opening of the dropdown content, relative to where it's positioned
     * @param {ComputePositionReturn} position - The position of the dropdown content
     */
    function animateOpen(position: ComputePositionReturn) {
        // dont animate if the user prefers reduced motion
        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches ?? false;
        gsap.from(content, {
            opacity: 0,
            scaleX: 0.95,
            scaleY: 0.95,
            transformOrigin: getTransformOrigin(position.placement),
            duration: reduceMotion ? 0 : 0.3,
        });
    }

    /**
     * Open the dropdown using @floating-ui/dom to position it relative to the trigger
     */
    async function open() {
        isOpen.value = true;
        content.style.display = "block"; // The element must be visible to correctly compute its position
        const position = await computePosition(button.value as ReferenceElement, content, {
            placement: "bottom-end",
            middleware: [flip(), shift()],
        });
        content.style.left = `${position.x}px`;
        content.style.top = `${position.y}px`;
        content.setAttribute("aria-hidden", "false");
        animateOpen(position);
    }

    /**
     * When the dropdown button is clicked, close the dropdown content if it's open,
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
        document.addEventListener("click", onDocumentClick);
    }

    /**
     * Get the content, the element that's displayed when the button is selected
     */
    function getContent() {
        content = document.querySelector(`#${contentId}[data-dropdown-content]`) as HTMLElement;
        if (content) {
            content.setAttribute("aria-hidden", "true");
        }
    }

    /**
     * Initialise the dropdown
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
