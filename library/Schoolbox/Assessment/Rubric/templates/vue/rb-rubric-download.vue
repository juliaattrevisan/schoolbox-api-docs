<script setup lang="ts">
import { downloadApiRequest } from "@js/util/sbx/apiUtils";
import type { RubricGrid } from "./types";
import { translateGridToFormPayload } from "./payload";

interface Props {
    rubricGrid: RubricGrid | null;
    // TODO: should really be able to do this without an assessment ID
    assessmentId: number | null;
    routeRubricDownload?: string | null;
}
const props = defineProps<Props>();

const parsedProps = window.Vue.computed(() => {
    // to disable the button if things are missing, props are all allowed to be null where relevant
    // this is an equivalent of props but only when it's possible to download, otherwise null
    if (props.rubricGrid === null || !props.routeRubricDownload || props.assessmentId === null) {
        return null; // can't download
    }

    return {
        rubricGrid: props.rubricGrid,
        routeRubricDownload: props.routeRubricDownload,
        assessmentId: props.assessmentId,
    };
});

/**
 * Download the rubric as a CSV
 */
async function download(): Promise<void> {
    if (parsedProps.value === null) {
        return; // shouldn't be possible to click the button in this state, but just in case
    }

    // This actually overrides our backend implementation in Rubric/Controller::postDownload() which attempts to
    // set the filename via Content-Disposition already. I spent a while trying to debug -that- implementation
    // only to realise that something else is taking precedence somehow...so here I am
    const filenameParts = [
        parsedProps.value.rubricGrid.title,
    ];

    const filename = filenameParts.filter(Boolean).join("-");

    const toast = window.sbProps.toastActivate("Downloading rubric...");
    const response = await downloadApiRequest({
        method: "POST",
        url: parsedProps.value.routeRubricDownload,
        body: {
            rubricGrid: translateGridToFormPayload(parsedProps.value.rubricGrid),
            assessmentId: parsedProps.value.assessmentId,
        },
        filename: `${encodeURIComponent(filename)}.csv`,
        accept: ["text/csv"],
    });

    if (!response.success) {
        toast.timeout({ text: "Failed to download rubric", isSuccess: false });
        return;
    }

    toast.timeout({ text: "Rubric downloaded", isSuccess: true });
}
</script>

<template>
    <button class="button sbx-button--has-icon" type="button" :disabled="!parsedProps" @click="download">
        <sbx-icon name="download"></sbx-icon>
        Download
    </button>
</template>
