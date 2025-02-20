/**
 * We found that the view status page was not able to efficiently load a large number of ckeditor instances.
 * To avoid this, the view status page now loads up to 30 instances at a time and paginates the rest of the students.
 */

import { slideDown, slideUp } from "@js/util/animateUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { SbxEditor } from "@js/editor/types/editorTypes";
import { ACTIVITY_STATUS_PAGE_INIT } from "./activityStatusPageEvent";

type ParticipantRow = {
    el: HTMLElement;
    textarea: HTMLTextAreaElement | null; // null if this participant doesn't have comments?
    name: string;
    editor: SbxEditor | null;
};

let participantRows: ParticipantRow[] = [];
let participantTabGroups: ParticipantRow[][] = [];

// The page config as specified when initialising the page using the event
let pageConfig: {
    pageItemLimit: number;
    elements: {
        paginator: {
            wrapper: HTMLElement;
            ul: HTMLUListElement;
        } | null; // null if no pagination
        filterEmptyState: HTMLElement;
        filterReset: HTMLElement;
        participantSearchInput: HTMLInputElement;
        participantFilters: HTMLInputElement[];
        participantRows: HTMLElement[];
    };
};

/**
 * Trigger the creation of a CKEditor instance for a given participant row
 * This will also handle the input events to keep the textarea in sync so that jquery.dynamic-feedback.js can track it
 * @param participantRow - The participant row to create the editor for
 * @returns A promise that resolves when the editor has been created
 */
async function createEditorForParticipant(participantRow: ParticipantRow): Promise<void> {
    // If no source element, nothing to do
    const sourceElement = participantRow.textarea;
    if (sourceElement === null) {
        return;
    }

    // TODO: I wonder if spawning them all via targetElements is more efficient?
    document.dispatchEvent(
        new CustomEvent(EditorEvent.EDITOR__CREATE, {
            detail: {
                targetElements: [sourceElement],
                hasTokens: true,
                showWordCount: true,
            },
        }),
    );

    // TODO: Use the TS module for spawning editors instead
    const editor = await new Promise<SbxEditor>((resolve) => {
        sourceElement.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
            resolve(event.detail.editor);
        });
    });

    participantRow.editor = editor;

    // on every input, update the textarea and send an input event on it too for jquery.dynamic-feedback.js
    editor.model.document.on("change", () => {
        sourceElement.value = editor.getData();
        sourceElement.dispatchEvent(new Event("change", { bubbles: true }));
    });
}

/**
 * Apply the visibility change for a participant row
 * @param participantRow - The participant row to apply the visibility change to
 * @param isActive - Whether the participant should be visible
 * @param animate - Whether to animate the change
 * @returns A promise that resolves when the visibility change has been applied
 */
async function applyParticipantVisibility(
    participantRow: ParticipantRow,
    isActive: boolean,
    animate: boolean,
): Promise<void> {
    if (!animate) {
        participantRow.el.style.display = isActive ? "" : "none";
        return;
    }

    // For slideDown (show), will not wait since we want to spawn the editor asap
    if (isActive) {
        // no need to slide down if it's already visible
        if (participantRow.el.style.display !== "none") {
            participantRow.el.dataset.display = "block"; // need this otherwise slideDown keeps it as none?
            void slideDown(participantRow.el);
        }
        return;
    }

    // For slideUp (hide), will wait for the animation to finish first to avoid seeing the textarea
    // no need to slide up if it's already hidden
    if (participantRow.el.style.display !== "none") {
        await slideUp(participantRow.el);
    }
}

/**
 * Create a page tab element
 * @param label - The label to display on the tab, string for arrows, number for page number
 * @param targetTab - The index of the tab to switch to when clicked
 * @param activeTab - The index of the currently active tab
 */
function createPageTab(label: string | number, targetTab: number, activeTab: number): HTMLLIElement {
    const tabLi = document.createElement("li");
    // When the label is a string, it's an arrow
    if (typeof label === "string") {
        tabLi.classList.add("arrow");
    }
    // When it's the active tab
    if (targetTab === activeTab) {
        tabLi.classList.add("active");
    }
    // If the target is out of bounds, make it unavailable
    const isUnavailable = targetTab < 0 || targetTab >= participantTabGroups.length;
    if (isUnavailable) {
        tabLi.classList.add("unavailable");
    }
    const tabA = document.createElement("a");
    tabA.href = "";
    tabA.textContent = String(label);
    tabLi.appendChild(tabA);

    if (!isUnavailable) {
        tabLi.addEventListener("click", (e) => {
            e.preventDefault();
            void onSelectedTabChange(targetTab, false);
        });
    }

    return tabLi;
}

/**
 * When the selected tab is changed, show the correct participants
 * @param activeTab - The index of the tab to show
 * @param animate - Whether to animate the change (slideUp/slideDown), use this for filters
 */
async function onSelectedTabChange(activeTab: number, animate: boolean): Promise<void> {
    if (pageConfig === undefined) throw new Error("Activity status page config missing");

    // Should we paginate?
    if (pageConfig.elements.paginator !== null) {
        const { wrapper, ul } = pageConfig.elements.paginator;
        // Show paginator only if more than one tab exists
        wrapper.style.display = participantTabGroups.length > 1 ? "" : "none";

        // Create the tabs
        if (participantTabGroups.length > 1) {
            ul.innerHTML = "";
            ul.appendChild(createPageTab("‹", activeTab - 1, activeTab));
            participantTabGroups.forEach((_, i) => {
                ul.appendChild(createPageTab(i + 1, i, activeTab));
            });
            ul.appendChild(createPageTab("›", activeTab + 1, activeTab));
        }
    }

    if (participantTabGroups.length === 0) {
        // everything was filtered out, so show the empty state
        pageConfig.elements.filterEmptyState.dataset.display = "block"; // need this otherwise slideDown keeps it as none?
        void slideDown(pageConfig.elements.filterEmptyState);
    } else {
        void slideUp(pageConfig.elements.filterEmptyState);
    }

    const activeGroup = participantTabGroups[activeTab]; // could be null if no groups
    await Promise.all(
        participantRows.map(async (participantRow) => {
            const isActive = activeGroup && activeGroup.includes(participantRow);
            const activeEditor = participantRow.editor;

            await applyParticipantVisibility(participantRow, isActive, animate);

            if (isActive && activeEditor === null) {
                // create a new editor
                await createEditorForParticipant(participantRow);
            } else if (!isActive && activeEditor !== null) {
                // destroy the existing editor
                participantRow.editor = null; // do this first to avoid double-destroying
                await activeEditor.destroy();
            }
        }),
    );
}

/**
 * When the search or filters change, show the correct participants
 */
async function onParticipantFilterChange(): Promise<void> {
    if (pageConfig === undefined) throw new Error("Activity status page config missing");

    const searchTerm = pageConfig.elements.participantSearchInput.value.toLowerCase();
    const activeFilters = pageConfig.elements.participantFilters
        .filter((el) => el.checked)
        .map((el) => el.dataset.status);

    const filteredParticipantRows = Array.from(participantRows).filter((participantRow) => {
        // If there is a search, and it doesn't match the name, don't show
        if (searchTerm.length && !participantRow.name.toLowerCase().includes(searchTerm)) {
            return false;
        }

        // If the status of the participant doesn't match the active filters, don't show
        if (!activeFilters.includes(participantRow.el.dataset.status)) {
            return false;
        }

        return true;
    });

    // should we paginate?
    participantTabGroups = [];
    if (pageConfig.elements.paginator !== null) {
        let currentGroup: ParticipantRow[] = [];

        filteredParticipantRows.forEach((participantRow) => {
            currentGroup.push(participantRow);
            if (currentGroup.length >= pageConfig.pageItemLimit) {
                participantTabGroups.push(currentGroup);
                currentGroup = [];
            }
        });

        if (currentGroup.length) {
            participantTabGroups.push(currentGroup);
        }
    } else if (filteredParticipantRows.length) {
        participantTabGroups.push(filteredParticipantRows);
    }

    await onSelectedTabChange(0, true); // reset to first page
}

document.addEventListener(ACTIVITY_STATUS_PAGE_INIT, (event) => {
    // parse the event detail and validate the elements exist
    const filterEmptyState = document.querySelector(event.detail.selectors.filterEmptyState);
    if (!(filterEmptyState instanceof HTMLElement)) {
        throw new Error("Unable to find filter empty state element");
    }
    const filterReset = document.querySelector(event.detail.selectors.filterReset);
    if (!(filterReset instanceof HTMLElement)) {
        throw new Error("Unable to find filter reset element");
    }
    const participantSearchInput = document.querySelector(event.detail.selectors.participantSearchInput);
    if (!(participantSearchInput instanceof HTMLInputElement)) {
        throw new Error("Unable to find participant search input element");
    }
    pageConfig = {
        pageItemLimit: event.detail.pageItemLimit,
        elements: {
            paginator: null, // to be set below if enabled
            filterEmptyState,
            filterReset,
            participantSearchInput,
            participantFilters: Array.from(
                document.querySelectorAll<HTMLInputElement>(event.detail.selectors.participantFilters),
            ),
            participantRows: Array.from(document.querySelectorAll<HTMLElement>(event.detail.selectors.participantRows)),
        },
    };

    // pagination is only used on some view status pages (wherever editors need to be spawned)
    // if a selector was provided but no element found, disable pagination (avoids needing extra twig blocks)
    const paginatorWrapper = event.detail.selectors.paginator
        ? document.querySelector(event.detail.selectors.paginator)
        : null;
    if (paginatorWrapper instanceof HTMLElement) {
        const paginatorUl = paginatorWrapper.querySelector("ul[class=pagination]");
        if (!(paginatorUl instanceof HTMLUListElement)) {
            throw new Error("Unable to find paginator list element");
        }
        pageConfig.elements.paginator = {
            wrapper: paginatorWrapper,
            ul: paginatorUl,
        };
    }

    participantRows = pageConfig.elements.participantRows.map((el) => {
        const participantName = el.querySelector(event.detail.selectors.participantRowName);
        if (!(participantName instanceof HTMLElement)) {
            throw new Error("Unable to find participant name element");
        }
        // We don't require a comment textarea for every participant row (e.g. tasks don't have comments)
        const participantCommentTextarea = el.querySelector(event.detail.selectors.participantRowCommentTextarea);
        if (participantCommentTextarea !== null && !(participantCommentTextarea instanceof HTMLTextAreaElement)) {
            throw new Error("Unable to find participant comment textarea element");
        }
        return {
            el,
            textarea: participantCommentTextarea,
            name: participantName.innerText,
            editor: null,
        };
    });

    // Add event listeners for when search/filters change
    // TODO: Debounce to avoid spamming creation/destruction of editors
    pageConfig.elements.participantSearchInput.addEventListener("input", () => void onParticipantFilterChange());
    pageConfig.elements.participantFilters.forEach((el) => {
        el.addEventListener("change", () => void onParticipantFilterChange());
    });

    // Allow the user to reset the search/filters when the empty state is shown
    pageConfig.elements.filterReset.addEventListener("click", (e) => {
        e.preventDefault();
        pageConfig.elements.participantSearchInput.value = "";
        pageConfig.elements.participantFilters.forEach((el) => {
            el.checked = true;
        });
        void onParticipantFilterChange();
    });

    // trigger initial filter and pagination (if needed)
    void onParticipantFilterChange();
});
