<script setup lang="ts">
/**
 * When selected displays a linked dropdown
 */
import { computePosition, flip, shift } from "@floating-ui/dom";

interface Props {
    dataDropdown: string; // the id of the linked dropdown to display
}
const props = defineProps<Props>();

const dropdownButton = window.Vue.ref<HTMLButtonElement | null>(null);
const dropdownContent = window.Vue.ref<HTMLElement | null>(null);

/**
 * Initialise the dropdown
 */
window.Vue.onMounted(() => {
    addEventListener("DOMContentLoaded", () => {
        dropdownContent.value = document.querySelector(`#${props.dataDropdown}[data-dropdown-content]`) as HTMLElement;
        if (!dropdownContent.value) {
            return;
        }

        dropdownButton.value.setAttribute("aria-haspopup", "true");
        dropdownButton.value.setAttribute("aria-expanded", "false");
        dropdownButton.value.setAttribute("aria-hidden", "true");
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

    dropdownContent.value.style.display = "block"; // The element must be visible to correctly compute its position

    computePosition(dropdownButton.value, dropdownContent.value, {
        placement: "bottom-end",
        middleware: [flip(), shift()],
    })
        .then(({ x, y }) => {
            window.sbProps.activeDropdown = {
                id: props.dataDropdown,
                content: dropdownContent.value,
                trigger: dropdownButton.value,
                close: (event?: MouseEvent) => {
                    // If the clicked target is inside the dropdown content and aria-autoclose is "false", do nothing
                    if (
                        event &&
                        dropdownContent.value.contains(event.target as Node) &&
                        dropdownContent.value.getAttribute("aria-autoclose") === "false"
                    ) {
                        return;
                    }

                    dropdownContent.value.style.display = "none";
                    dropdownContent.value.setAttribute("aria-hidden", "true");
                    dropdownButton.value.setAttribute("aria-expanded", "false");

                    if (window.sbProps.activeDropdown) {
                        document.removeEventListener("click", window.sbProps.activeDropdown.close);
                        window.sbProps.activeDropdown = false;
                    }
                },
            };
            dropdownContent.value.style.left = `${x}px`;
            dropdownContent.value.style.top = `${y}px`;

            dropdownContent.value.setAttribute("aria-hidden", "false");
            dropdownButton.value.setAttribute("aria-expanded", "true");

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
        ref="dropdownButton"
        type="button"
        class="c-button c-button--icon-only c-button--no-bkg"
        aria-label="Open drop down"
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
