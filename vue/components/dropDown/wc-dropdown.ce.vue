<script setup lang="ts">
/**
 * When selected displays a linked dropdown
 */
import { ReferenceElement, computePosition, flip, shift } from "@floating-ui/dom";

interface Props {
    dataDropdown: string; // the id to target the dropdownContent to display
}
const props = defineProps<Props>();

const dropdownTrigger = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const isOpen = window.Vue.ref(false); // Whether the dropdown is open or not
let dropdownContent: HTMLElement; // The dropdown content, the element that's displayed when the button is selected

/**
 * Close the dropdown if it's open
 */
function close() {
    if (isOpen.value) {
        isOpen.value = false;
        dropdownContent.style.display = "none";
        dropdownContent.setAttribute("aria-hidden", "true");
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
        dropdownContent.contains(event.target as Node) &&
        dropdownContent.getAttribute("aria-autoclose") === "false"
    ) {
        return;
    }
    // If the clicked target is the dropdown trigger, also do nothing
    if (
        event &&
        event.target &&
        (event.target as HTMLElement).hasAttribute("data-dropdown") &&
        (event.target as HTMLElement).getAttribute("data-dropdown") === props.dataDropdown
    ) {
        return;
    }
    close();
    document.removeEventListener("click", onDocumentClick);
}

/**
 * When the dropdown button is clicked, close the dropdown content if it's open,
 * otherwise open it using @floating-ui/dom to position it relative to the trigger
 * while also trying to keep it in the viewport
 */
async function onButtonSelected() {
    if (isOpen.value) {
        close();
        return;
    }

    dropdownContent.style.display = "block"; // The element must be visible to correctly compute its position

    const position = await computePosition(dropdownTrigger.value as ReferenceElement, dropdownContent, {
        placement: "bottom-end",
        middleware: [flip(), shift()],
    });

    dropdownContent.style.left = `${position.x}px`;
    dropdownContent.style.top = `${position.y}px`;
    dropdownContent.setAttribute("aria-hidden", "false");

    isOpen.value = true;

    document.addEventListener("click", onDocumentClick);
}

/**
 * Get the dropdown content, the element that's displayed when the button is selected
 */
function getDropdownContent() {
    window.addEventListener("DOMContentLoaded", () => {
        dropdownContent = document.querySelector(`#${props.dataDropdown}[data-dropdown-content]`) as HTMLElement;
        if (!dropdownContent) {
            return;
        }
        dropdownContent.setAttribute("aria-hidden", "true");
    });
}

/**
 * Initialise the dropdown
 */
window.Vue.onMounted(() => {
    getDropdownContent();
});
</script>

<template>
    <button
        ref="dropdownTrigger"
        type="button"
        class="c-button c-button--icon-only c-button--no-bkg f-match-existing-styles"
        aria-label="Open drop down"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        @click.prevent="onButtonSelected"
    >
        <svg
            version="1.2"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 16 16"
            width="16"
            height="16"
            aria-hidden="true"
            focusable="false"
            class="c-icon"
        >
            <path d="m6 0h4v4h-4z" />
            <path d="m6 6h4v4h-4z" />
            <path d="m6 12h4v4h-4z" />
        </svg>
    </button>
    <!--
        <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions" id="all-actions" data-dropdown-initialized="true" aria-haspopup="true" aria-expanded="true"></a>
    -->
</template>

<style lang="scss" scoped>
// One current complication of using Vue and Web components is the inability to avoid the shadow DOM
// This means that we cannot use the global styles to style the component
// It looks like the ability to opt out of the shadow DOM is hopefully coming soon however
@import "../../../www/static/css/core/ui/button/_c-button.scss";

// To match the typical background colour of the dropdown content
button[aria-expanded="true"] {
    background-color: var(--content-ui-background);
}

// As we aren't quite ready to introduce the new button styles completely, we add these to match the existing styles as a stop gap
button.f-match-existing-styles {
    height: 42px !important;
    min-height: 42px !important;
    width: 40px !important;
    &:focus {
        box-shadow: 0 0 0 2px currentColor;
    }
}
</style>
