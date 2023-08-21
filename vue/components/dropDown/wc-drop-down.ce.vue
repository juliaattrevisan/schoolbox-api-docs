<script setup lang="ts">
/**
 * When selected displays a linked dropdown
 */
import { ReferenceElement, computePosition, flip, shift } from "@floating-ui/dom";

interface Props {
    dataDropdown: string; // the id of the linked dropdown to display
}
const props = defineProps<Props>();

const dropdownTrigger = window.Vue.ref<HTMLButtonElement | null>(null);
let dropdownContent: HTMLElement;
const isOpen = window.Vue.ref(false);

/**
 * Initialise the dropdown
 */
window.Vue.onMounted(() => {
    window.addEventListener("DOMContentLoaded", () => {
        dropdownContent = document.querySelector(`#${props.dataDropdown}[data-dropdown-content]`) as HTMLElement;
        if (!dropdownContent) {
            return;
        }
        dropdownContent.setAttribute("aria-hidden", "true");
    });
});

/**
 * When the dropdown button is clicked
 */
function onButtonSelected() {
    // If there's an active dropdown, close it
    //  This check applies when the user clicks on any dropdown trigger
    //  As we have stopped the propagation of the click event, the event listener on the document won't be triggered
    //  If the user clicks anywhere else on the page, the dropdown will be closed by a different event listener
    const currentActiveDropdown = window.sbProps.activeDropdown;
    if (currentActiveDropdown) {
        currentActiveDropdown.close();

        // Prevent the dropdown from reopening
        if (currentActiveDropdown.id === props.dataDropdown) {
            return;
        }
    }

    dropdownContent.style.display = "block"; // The element must be visible to correctly compute its position

    computePosition(dropdownTrigger.value as ReferenceElement, dropdownContent, {
        placement: "bottom-end",
        middleware: [flip(), shift()],
    })
        .then(({ x, y }) => {
            window.sbProps.activeDropdown = {
                id: props.dataDropdown,
                trigger: dropdownTrigger.value as HTMLButtonElement,
                content: dropdownContent,
                close: (event?: MouseEvent) => {
                    // If the clicked target is inside the dropdown content and aria-autoclose is "false", do nothing
                    if (
                        event &&
                        dropdownContent.contains(event.target as Node) &&
                        dropdownContent.getAttribute("aria-autoclose") === "false"
                    ) {
                        return;
                    }

                    dropdownContent.style.display = "none";
                    dropdownContent.setAttribute("aria-hidden", "true");
                    isOpen.value = false;

                    if (window.sbProps.activeDropdown) {
                        document.removeEventListener("click", window.sbProps.activeDropdown.close);
                        window.sbProps.activeDropdown = false;
                    }
                },
            };
            dropdownContent.style.left = `${x}px`;
            dropdownContent.style.top = `${y}px`;

            dropdownContent.setAttribute("aria-hidden", "false");
            isOpen.value = true;

            // Add an event listener to close the dropdown when the user clicks anywhere outside the content
            document.addEventListener("click", window.sbProps.activeDropdown.close);
        })
        .catch(() => {
            // noop
        });
}
</script>

<template>
    <button
        ref="dropdownTrigger"
        type="button"
        class="c-button c-button--icon-only c-button--no-bkg"
        aria-label="Open drop down"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        @click.stop.prevent="onButtonSelected"
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
</style>
