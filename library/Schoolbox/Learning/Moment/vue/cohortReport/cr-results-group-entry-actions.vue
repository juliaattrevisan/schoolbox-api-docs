<script lang="ts" setup>
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { useCohortReportStore } from "./store/cohortReportStore";
import { CohortReportModalType, useCohortReportModalStore } from "./store/cohortReportModalStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    entry: CohortReportEntry;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();
const crmStore = useCohortReportModalStore();

/**
 * Approve this entry
 */
async function onApprove(): Promise<void> {
    if (props.entry.hasComments) {
        // If there are comments, open the confirmation modal to ask if they want to delete them
        crmStore.openModal(props.entry, CohortReportModalType.CONFIRM_PUBLISH);
        return;
    }

    // Otherwise just publish immediately
    await crStore.publishEntries([props.entry], false);
}
</script>

<template>
    <li v-if="props.entry._links.publish">
        <a class="icon-approve" @click.prevent="onApprove"> Approve </a>
    </li>
    <li v-if="props.entry._links.reject">
        <a class="icon-surveys" @click.prevent="crmStore.openModal(props.entry, CohortReportModalType.CONFIRM_REJECT)">
            Request Update
        </a>
    </li>
    <li v-if="props.entry._links.modifyForm">
        <a class="icon-modify" :href="getReturnUrl(props.entry._links.modifyForm)"> Modify </a>
    </li>
    <li v-if="props.entry._links.delete">
        <a class="icon-delete" @click.prevent="crmStore.openModal(props.entry, CohortReportModalType.CONFIRM_DELETE)">
            Delete
        </a>
    </li>
</template>

<style scoped></style>
