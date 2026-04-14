<script setup lang="ts">
import { fromEntriesOf, keysOf } from "@js/util/arrayUtils";
import { assertUnreachable } from "@js/util/errorUtils";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import { EvidenceStatus, evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";
import { CohortReportModalType, useCohortReportModalStore } from "./store/cohortReportModalStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

const crStore = useCohortReportStore();
const crmStore = useCohortReportModalStore();

/**
 * Since we can select results of multiple types, disable the buttons that aren't going to do anything
 */
const selectedResultActions = window.Vue.computed(() => {
    const actionTypes = ["delete", "publish", "reject"] as const;
    const actionEntries: Record<(typeof actionTypes)[number], CohortReportEntry[]> = fromEntriesOf(
        actionTypes.map((type) => [type, []]),
    );

    const selectedEntryIds = keysOf(crStore.selectedResults).filter((id) => crStore.selectedResults[id]);
    selectedEntryIds.forEach((entryId) => {
        const entry = crStore.results.find((e) => String(e.id) === String(entryId));
        if (!entry) {
            return; // entry not found, skip
        }

        actionTypes.forEach((type) => {
            if (type === "publish" && entry.currentState === EvidenceStatus.PUBLISHED) {
                // Approve on the cohort report is special, if it's already approved then you'll still technically have access
                // to re-approve it, but showing this button may be confusing in the workflow. So hide it if it's already published.
                // This aims to avoid deletion of comments on already published items by mistake
                return;
            }
            if (entry._links[type] !== null) {
                actionEntries[type].push(entry);
            }
        });
    });

    return actionEntries;
});

const selectedResultStatuses = window.Vue.computed(() => {
    const statuses = keysOf(crStore.selectedResults).reduce((acc, key) => {
        const entry = crStore.results.find((e) => String(e.id) === String(key));
        if (!entry || !crStore.selectedResults[key]) {
            return acc; // entry not found/selected, skip
        }

        const amt = acc.get(entry.currentState) || 0;
        acc.set(entry.currentState, amt + 1);

        return acc;
    }, new Map<CohortReportEntry["currentState"], number>());

    const labelKeys = keysOf(evidenceStatusLabels);

    return Array.from(statuses.entries())
        .sort((a, b) => labelKeys.indexOf(a[0]) - labelKeys.indexOf(b[0]))
        .map(([state, count]) => `${count} ${evidenceStatusLabels[state]}`)
        .join(", ")
        .toLowerCase();
});

const totalSelectedResults = window.Vue.computed(() => {
    return Object.values(crStore.selectedResults).filter((selected) => !!selected).length;
});

/**
 * Clear all selected results
 */
function clearSelection(): void {
    keysOf(crStore.selectedResults).forEach((key) => {
        delete crStore.selectedResults[key];
    });
}

/**
 * Handle the action based on the type
 * @param type The action type to perform
 */
async function onAction(type: keyof typeof selectedResultActions.value): Promise<void> {
    if (selectedResultActions.value[type].length === 0) {
        return; // no entries to perform action on
    }

    const entries = selectedResultActions.value[type];

    if (type === "delete") {
        crmStore.openModal(entries, CohortReportModalType.CONFIRM_DELETE, clearSelection);
    } else if (type === "publish") {
        if (
            entries.some(
                (entry) => entry.discussionThread !== null && entry.discussionThread.children.metadata.count > 0,
            )
        ) {
            // If there are comments, open the confirmation modal to ask if they want to delete them
            crmStore.openModal(entries, CohortReportModalType.CONFIRM_PUBLISH, clearSelection);
        } else {
            // Otherwise just publish immediately
            await crStore.publishEntries(entries, false);
        }
    } else if (type === "reject") {
        crmStore.openModal(entries, CohortReportModalType.CONFIRM_REJECT, clearSelection);
    } else {
        assertUnreachable(type);
    }
}
</script>

<template>
    <bottom-drawer :show-drawer="totalSelectedResults > 0">
        <h3 class="selected-count l-mar-s--right">
            <em>{{ totalSelectedResults }} records selected</em>
        </h3>
        <div class="meta l-mar-s--right">({{ selectedResultStatuses }})</div>
        <button class="button-clear" @click.prevent="clearSelection">Clear selection</button>

        <div class="l-flex-row l-flex--justify l-gap-xs">
            <button
                class="button l-flex__item--1-auto"
                :disabled="selectedResultActions.delete.length === 0"
                @click="onAction('delete')"
            >
                <sbx-icon name="delete"></sbx-icon>
                Delete
            </button>
            <button
                class="button l-flex__item--1-auto icon-surveys"
                :disabled="selectedResultActions.reject.length === 0"
                @click="onAction('reject')"
            >
                Request Update
            </button>
        </div>
        <div class="l-flex-row l-flex--justify l-gap-xs">
            <button
                class="button l-flex__item--1-auto submit"
                :disabled="selectedResultActions.publish.length === 0"
                @click="onAction('publish')"
            >
                <sbx-icon name="approve"></sbx-icon>
                Publish
            </button>
        </div>
    </bottom-drawer>
</template>

<style scoped lang="scss">
.button-clear,
.selected-count,
.meta {
    display: inline-block;
}

.button-clear {
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
    &:hover {
        background: none;
    }
}
</style>
