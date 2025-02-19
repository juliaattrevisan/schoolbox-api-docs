/**
 * We found that the view status page was not able to efficiently load a large number of ckeditor instances.
 * To avoid this, the view status page now loads up to 30 instances at a time and paginates the rest of the students.
 */

import { slideDown, slideUp } from "@js/util/animateUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { SbxEditor } from "@js/editor/types/editorTypes";
import { ACTIVITY_STATUS_PAGE_INIT } from "./activityStatusPageEvent";
import type { AcitivityStatusPageConfig } from "./activityStatusPageEvent";

type ParticipantRow = {
    el: HTMLElement;
    textarea: HTMLTextAreaElement | null; // null if this participant doesn't have comments?
    name: string;
    editor: SbxEditor | null;
};

const participantRows: ParticipantRow[] = [];
let participantTabGroups: ParticipantRow[][] = [];

// The page config as specified when initialising the page using the event
let pageConfig: AcitivityStatusPageConfig;

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
        void slideDown(participantRow.el);
        return;
    }

    // For slideUp (hide), will wait for the animation to finish first to avoid seeing the textarea
    await slideUp(participantRow.el);
}

/**
 * When the selected tab is changed, show the correct participants
 * @param activeTab - The index of the tab to show
 * @param animate - Whether to animate the change (slideUp/slideDown), use this for filters
 */
async function onSelectedTabChange(activeTab: number, animate: boolean): Promise<void> {
    if (pageConfig === undefined) throw new Error("Activity status page config missing");

    // Should we paginate?
    if (pageConfig.selectors.paginator) {
        const paginator = document.querySelector(pageConfig.selectors.paginator);
        if (!(paginator instanceof HTMLElement)) {
            throw new Error("Unable to find paginator element");
        }
        const paginatorUl = paginator.querySelector("ul[class=pagination]");
        if (!(paginatorUl instanceof HTMLElement)) {
            throw new Error("Unable to find paginator list element");
        }
        paginator.style.display = participantTabGroups.length > 1 ? "" : "none";

        if (participantTabGroups.length > 1) {
            const tabs = participantTabGroups.map((group, i) => {
                return `<li class="${i === activeTab ? "active" : ""}" data-participant-tab=${i}><a href="">${i + 1}</a></li>`;
            });

            paginatorUl.innerHTML = `
                <li class="arrow ${activeTab === 0 ? "unavailable" : ""}" data-participant-tab="${activeTab - 1}"><a href="">‹</a></li>
                    ${tabs.join("")}
                <li class="arrow ${activeTab === participantTabGroups.length - 1 ? "unavailable" : ""}" data-participant-tab="${activeTab + 1}"><a href="">›</a></li>`;

            paginator.querySelectorAll("[data-participant-tab]").forEach((el, i) => {
                el.addEventListener("click", (e) => {
                    e.preventDefault();
                    if (el.classList.contains("unavailable")) {
                        return;
                    }
                    void onSelectedTabChange(parseInt(el.getAttribute("data-participant-tab"), 10), false);
                });
            });
        }
    }

    const filterEmptyState = document.querySelector(pageConfig.selectors.filterEmptyState);
    if (!(filterEmptyState instanceof HTMLElement)) {
        throw new Error("Unable to find filter empty state element");
    }

    if (participantTabGroups.length === 0) {
        // everything was filtered out, so show the empty state
        filterEmptyState.dataset.display = "block";
        void slideDown(filterEmptyState);
    } else {
        void slideUp(filterEmptyState);
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

    const participantSearchInput = document.querySelector(pageConfig.selectors.participantSearchInput);
    if (!(participantSearchInput instanceof HTMLInputElement)) {
        throw new Error("Unable to find participant search input element");
    }
    const participantFilters = document.querySelectorAll<HTMLInputElement>(pageConfig.selectors.participantFilters);

    const searchTerm = participantSearchInput.value.toLowerCase();
    const activeFilters = Array.from(participantFilters)
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
    if (pageConfig.selectors.paginator) {
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
    pageConfig = event.detail;

    participantRows.push(
        ...Array.from(document.querySelectorAll(pageConfig.selectors.participantRows)).map((el) => {
            return {
                el: el as HTMLElement,
                textarea: el.querySelector<HTMLTextAreaElement>("[data-feedback-comment]"),
                name: (el.querySelector("[data-participant-name]") as HTMLElement).innerText,
                editor: null,
            };
        }),
    );

    const participantSearchInput = document.querySelector(pageConfig.selectors.participantSearchInput);
    const participantFilters = document.querySelectorAll(pageConfig.selectors.participantFilters);
    const filterReset = document.querySelector(pageConfig.selectors.filterReset);

    // Add event listeners for when search/filters change
    // TODO: Debounce to avoid spamming creation/destruction of editors
    participantSearchInput.addEventListener("input", () => void onParticipantFilterChange());
    participantFilters.forEach((el) => {
        el.addEventListener("change", () => void onParticipantFilterChange());
    });

    // Allow the user to reset the search/filters when the empty state is shown
    filterReset.addEventListener("click", (e) => {
        e.preventDefault();
        participantSearchInput.value = "";
        participantFilters.forEach((el) => {
            el.checked = true;
        });
        void onParticipantFilterChange();
    });

    // trigger initial filter and pagination (if needed)
    void onParticipantFilterChange();
});
