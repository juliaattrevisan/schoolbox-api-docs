import { computePosition, flip, shift } from "@floating-ui/dom";

/**
 * Initialize a dropdown.
 * @param {HTMLElement} dropdownTrigger The trigger element of the dropdown.
 */
function initializeDropdown(dropdownTrigger: HTMLElement) {
    // Prevent the dropdown from being initialized multiple times
    if (dropdownTrigger.getAttribute("data-dropdown-initialized") === "true") {
        return;
    }
    dropdownTrigger.setAttribute("data-dropdown-initialized", "true");

    const dropdownId = dropdownTrigger.getAttribute("data-dropdown") as string;
    const dropdownContent = document.querySelector(`#${dropdownId}[data-dropdown-content]`) as HTMLElement;

    if (!dropdownContent) {
        return;
    }

    dropdownTrigger.setAttribute("aria-haspopup", "true");
    dropdownTrigger.setAttribute("aria-expanded", "false");
    dropdownContent.setAttribute("aria-hidden", "true");

    dropdownTrigger.addEventListener("click", (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        // If there's an active dropdown, close it
        //  This check applies when the user clicks on any dropdown trigger
        //  As we have stopped the propagation of the click event, the event listener on the document won't be triggered
        //  If the user clicks anywhere else on the page, the dropdown will be closed by a different event listener
        const currentActiveDropdown = window.sbProps.activeDropdown;
        if (currentActiveDropdown) {
            currentActiveDropdown.close();

            // Prevent the dropdown from reopening
            if (currentActiveDropdown.id === dropdownId) {
                return;
            }
        }

        dropdownContent.style.display = "block"; // The element must be visible to correctly compute its position

        computePosition(dropdownTrigger, dropdownContent, {
            placement: "bottom-end",
            middleware: [flip(), shift()],
        })
            .then(({ x, y }) => {
                window.sbProps.activeDropdown = {
                    id: dropdownId,
                    content: dropdownContent,
                    trigger: dropdownTrigger,
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
                        dropdownTrigger.setAttribute("aria-expanded", "false");

                        if (window.sbProps.activeDropdown) {
                            document.removeEventListener("click", window.sbProps.activeDropdown.close);
                            window.sbProps.activeDropdown = false;
                        }
                    },
                };
                dropdownContent.style.left = `${x}px`;
                dropdownContent.style.top = `${y}px`;

                dropdownContent.setAttribute("aria-hidden", "false");
                dropdownTrigger.setAttribute("aria-expanded", "true");

                // Add an event listener to close the dropdown when the user clicks anywhere outside the content
                document.addEventListener("click", window.sbProps.activeDropdown.close);
            })
            .catch(() => {
                // noop
            });
    });
}

/*
 * Initialize dropdowns on DOMContentLoaded
 */
// document.addEventListener("DOMContentLoaded", () => {
//     // Initialize the activeDropdown property if it doesn't exist
//     window.sbProps.activeDropdown = window.sbProps.activeDropdown ?? false;

//     const dropdowns = document.querySelectorAll("[data-dropdown]");
//     dropdowns.forEach((dropdownTrigger) => {
//         initializeDropdown(dropdownTrigger as HTMLElement);
//     });
// });

/*
 * Initialize dropdowns on DROPDOWN__INIT event (In case the dropdown is added to the DOM after DOMContentLoaded)
 */
// document.addEventListener("DROPDOWN__INIT", () => {
//     const dropdowns = document.querySelectorAll("[data-dropdown]");
//     dropdowns.forEach((dropdownTrigger) => {
//         initializeDropdown(dropdownTrigger as HTMLElement);
//     });
// });
