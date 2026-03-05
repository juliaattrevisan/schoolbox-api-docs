/*
 * The following is a Vanilla JS Typescsript simplified conversion of jQuery Chained: https://github.com/tuupola/jquery_chained for our current needs
 * It's currently only used in the Pastoral module, to update the subtype options based on the selected type,
 * but it is designed to be reusable and easily extendable to other use cases.
 * Usage: window.sbx.chained(childSelector, parentSelector)
 */

/**
 * Type for child/parent selectors - can be a CSS selector string or HTMLSelectElement
 */
type ElementSelector = string | HTMLSelectElement;

/**
 * API returned by chained function
 */
interface ChainedAPI {
    /**
     * Manually trigger an update of the child select options
     */
    update: () => void;
    /**
     * Destroy the chained behavior and remove event listeners
     */
    destroy: () => void;
}

/**
 * Initialize chained select behavior
 * @param childSelector - The child select element or selector
 * @param parentSelector - The parent select element or selector
 * @returns API object with update and destroy methods, or undefined if initialization fails
 */
function chained(childSelector: ElementSelector, parentSelector: ElementSelector): ChainedAPI | undefined {
    // Get the child element
    const childElement: HTMLSelectElement | null =
        typeof childSelector === "string" ? document.querySelector<HTMLSelectElement>(childSelector) : childSelector;

    if (!childElement) {
        console.error("Chained: Child element not found");
        return undefined;
    }

    // Now we know child is not null, assign to non-nullable constant
    const child: HTMLSelectElement = childElement;

    // Store original options for restoration
    const backup: HTMLSelectElement = child.cloneNode(true) as HTMLSelectElement;

    // Get the parent element
    const parentElement: HTMLSelectElement | null =
        typeof parentSelector === "string" ? document.querySelector<HTMLSelectElement>(parentSelector) : parentSelector;

    if (!parentElement) {
        console.error("Chained: Parent element not found");
        return undefined;
    }

    const parent: HTMLSelectElement = parentElement;

    /**
     * Update child select options based on parent selection
     */
    function updateChildren(): void {
        let triggerChange = true;
        const currentlySelectedValue: string = child.value;

        // Restore all original options
        child.innerHTML = backup.innerHTML;

        // Get parent selection value
        const selectedValue: string = parent.value;

        // Filter options based on data-chained attribute
        const options: HTMLOptionElement[] = Array.from(child.querySelectorAll<HTMLOptionElement>("option"));
        options.forEach((option: HTMLOptionElement) => {
            // Always keep the default empty value option
            if (option.value === "") {
                return;
            }

            const chainedData: string | null = option.getAttribute("data-chained");
            if (!chainedData) {
                option.remove();
                return;
            }

            const matches: string[] = chainedData.trim().split(/\s+/);
            const isMatch: boolean = matches.includes(selectedValue);

            if (isMatch) {
                // Keep this option
                if (option.value === currentlySelectedValue) {
                    option.selected = true;
                    triggerChange = false;
                }
            } else {
                // Remove this option
                option.remove();
            }
        });

        // Disable select if only default option remains
        const remainingOptions: NodeListOf<HTMLOptionElement> = child.querySelectorAll<HTMLOptionElement>("option");
        if (remainingOptions.length === 1 && child.value === "") {
            child.disabled = true;
        } else {
            child.disabled = false;
        }

        // Trigger change event if selection changed
        if (triggerChange) {
            child.dispatchEvent(new Event("change", { bubbles: true }));
        }
    }

    // Attach change handler to parent
    parent.addEventListener("change", updateChildren);

    // Force first option to be selected if nothing is selected
    if (!parent.querySelector("option:checked") && !parent.value) {
        const firstOption: HTMLOptionElement | null = parent.querySelector("option");
        if (firstOption) {
            firstOption.selected = true;
        }
    }

    // Initial update
    updateChildren();

    // Return API for potential future use
    return {
        update: updateChildren,
        destroy: (): void => {
            parent.removeEventListener("change", updateChildren);
        },
    };
}

// Export to global scope
if (typeof window !== "undefined") {
    window.sbx = window.sbx || ({} as Window["sbx"]);
    window.sbx.chained = chained;
}

// ES Module exports
export { chained };
export type { ElementSelector, ChainedAPI };

// Default export
export default chained;
