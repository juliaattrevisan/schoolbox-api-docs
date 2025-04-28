/**
 * We found that the view status page was not able to efficiently load a large number of ckeditor instances.
 * To avoid this, the view status page now loads up to 30 instances at a time and paginates the rest of the students.
 */

import debounce from "lodash/debounce";
import { slideDown, slideUp } from "@js/util/animateUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { EditorCreateEventInfo } from "@js/editor/event/editorEvent";
import { EditorToolbar } from "@js/editor/types/editorTypes";
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
    pageItemLimitOffset: number;
    elements: {
        paginator: {
            wrapper: HTMLElement;
            ul: HTMLUListElement;
        } | null; // null if no pagination
        filterEmptyState: HTMLElement;
        filterReset: HTMLElement;
        participantLoader: HTMLElement;
        participantContainer: HTMLElement;
        participantSearchInput: HTMLInputElement | null;
        participantFilters: HTMLInputElement[];
        participantRows: HTMLElement[];
    };
};

/**
 * Wait for the editor to be created for a participant row
 * @param participantRow - The participant row to wait for the editor on
 */
async function waitForParticipantEditor(participantRow: ParticipantRow): Promise<void> {
    const sourceElement = participantRow.textarea;
    if (sourceElement === null) {
        throw new Error("Participant row does not have a textarea");
    }

    const editor = await new Promise<SbxEditor>((resolve) => {
        sourceElement.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
            resolve(event.detail.editor);
        });
    });

    participantRow.editor = editor;
}

/**
 * Trigger the creation of a CKEditor instance for participant row(s)
 * @param participants - The participant rows to create the editor for
 * @returns A promise that resolves when the editors have all been created
 */
async function createEditorForParticipants(participants: ParticipantRow[]): Promise<void> {
    // If no source elements, nothing to do
    const validParticipantRows = participants.filter((participantRow) => participantRow.textarea !== null) as (Omit<
        ParticipantRow,
        "textarea"
    > & { textarea: HTMLTextAreaElement })[];
    if (validParticipantRows.length === 0) {
        return;
    }

    document.dispatchEvent(
        new CustomEvent<EditorCreateEventInfo>(EditorEvent.EDITOR__CREATE, {
            detail: {
                targetElements: validParticipantRows.map((participantRow) => participantRow.textarea),
                hasTokens: true,
                showWordCount: true,
                maxCharCount: window.sbProps.MAX_BYTES_MYSQL_MEDIUMTEXT,
                toolbar: EditorToolbar.COMMENT_AI,
                mirrorChangeEvents: true, // To keep jquery.dynamic-feedback.js in sync
            },
        }),
    );

    await Promise.all(
        validParticipantRows.map((participantRow) => {
            return waitForParticipantEditor(participantRow);
        }),
    );
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
        if (participantRow.el.style.display === "none") {
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
 * @returns The created tab element
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

    tabLi.addEventListener("click", (e) => {
        e.preventDefault(); // always prevent default even if unavailable
        if (!isUnavailable && targetTab !== activeTab) {
            // Scroll to the top of the page and show the loader
            pageConfig.elements.participantLoader.classList.remove("hide");
            pageConfig.elements.participantContainer.classList.add("hide");
            if (pageConfig.elements.paginator) {
                pageConfig.elements.paginator.wrapper.style.display = "none";
            }
            document.querySelector("#header")?.scrollIntoView();
            // A slight delay to allow the scroll to start before the tab change hangs the page
            setTimeout(() => {
                // eslint-disable-next-line @typescript-eslint/no-use-before-define -- intentional recursion for now
                void changeTab(targetTab, false);
            }, 100);
        }
    });

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
    const editorsToCreate: ParticipantRow[] = [];
    await Promise.all(
        participantRows.map(async (participantRow) => {
            const isActive = activeGroup && activeGroup.includes(participantRow);
            const activeEditor = participantRow.editor;

            await applyParticipantVisibility(participantRow, isActive, animate);

            if (isActive && activeEditor === null) {
                // create a new editor
                // await createEditorForParticipant(participantRow);
                editorsToCreate.push(participantRow);
            } else if (!isActive && activeEditor !== null) {
                // destroy the existing editor
                participantRow.editor = null; // do this first to avoid double-destroying
                await activeEditor.destroy();
            }
        }),
    );

    // Create all the editors at once
    await createEditorForParticipants(editorsToCreate);

    // Remove the loader and show the participants
    pageConfig.elements.participantLoader.classList.add("hide");
    pageConfig.elements.participantContainer.classList.remove("hide");
}

let changeTabLock: Promise<void> | null = null;
let changeTabNext: (() => Promise<void>) | null = null;

/**
 * To change the selected tab
 * This ensures that only one call of onSelectedTabChange is running at a time to avoid concurrent editor creation/destruction
 * @param activeTab - The index of the tab to show
 * @param animate - Whether to animate the change (slideUp/slideDown), use this for filters
 */
async function changeTab(activeTab: number, animate: boolean): Promise<void> {
    // If there is a call in progress, set this as the next call to make after it
    if (changeTabLock !== null) {
        changeTabNext = async (): Promise<void> => {
            await changeTab(activeTab, animate);
        };
        return;
    }

    // Before we start a call, clear any queued calls (which is probably what called this)
    changeTabNext = null;

    // Now change the tab
    changeTabLock = onSelectedTabChange(activeTab, animate);
    try {
        await changeTabLock;
    } finally {
        changeTabLock = null;
        // If there is a queued call, start it now
        if (typeof changeTabNext === "function") {
            // @ts-expect-error: TS doesn't seem to understand that changeTabNext may have changed during the await
            await changeTabNext();
        }
    }
}

/**
 * When the search or filters change, show the correct participants
 */
async function onParticipantFilterChange(): Promise<void> {
    if (pageConfig === undefined) throw new Error("Activity status page config missing");

    const searchTerm = pageConfig.elements.participantSearchInput
        ? pageConfig.elements.participantSearchInput.value.toLowerCase()
        : "";
    const activeFilters = pageConfig.elements.participantFilters
        .filter((el) => el.checked)
        .map((el) => el.dataset.status);

    const filteredParticipantRows = participantRows.filter((participantRow) => {
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
    if (
        pageConfig.elements.paginator !== null &&
        filteredParticipantRows.length > pageConfig.pageItemLimit + pageConfig.pageItemLimitOffset
    ) {
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

    await changeTab(0, true); // reset to first page
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
    const participantLoader = document.querySelector(event.detail.selectors.participantLoader);
    if (!(participantLoader instanceof HTMLElement)) {
        throw new Error("Unable to find participant loader element");
    }
    const participantContainer = document.querySelector(event.detail.selectors.participantContainer);
    if (!(participantContainer instanceof HTMLElement)) {
        throw new Error("Unable to find participant container element");
    }
    // We don't require a participant search input for every page
    const participantSearchInput = event.detail.selectors.participantSearchInput
        ? document.querySelector(event.detail.selectors.participantSearchInput)
        : null;
    if (participantSearchInput !== null && !(participantSearchInput instanceof HTMLInputElement)) {
        throw new Error("Unable to find participant search input element");
    }
    pageConfig = {
        pageItemLimit: event.detail.pageItemLimit,
        pageItemLimitOffset: event.detail.pageItemLimitOffset,
        elements: {
            paginator: null, // to be set below if enabled
            filterEmptyState,
            filterReset,
            participantLoader,
            participantContainer,
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
    // Create a debounced version of onParticipantFilterChange to avoid spamming editor creation/destruction.
    const debouncedFilterChange = debounce(onParticipantFilterChange, 300);

    if (pageConfig.elements.participantSearchInput !== null) {
        pageConfig.elements.participantSearchInput.addEventListener("input", () => void debouncedFilterChange());
    }
    pageConfig.elements.participantFilters.forEach((el) => {
        el.addEventListener("change", () => void debouncedFilterChange());
    });

    // Allow the user to reset the search/filters when the empty state is shown
    pageConfig.elements.filterReset.addEventListener("click", (e) => {
        e.preventDefault();
        if (pageConfig.elements.participantSearchInput !== null) {
            pageConfig.elements.participantSearchInput.value = "";
        }
        pageConfig.elements.participantFilters.forEach((el) => {
            el.checked = true;
        });
        void onParticipantFilterChange();
    });

    // trigger initial filter and pagination (if needed)
    void onParticipantFilterChange();
});
