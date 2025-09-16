<script setup lang="ts">
import type { Template } from "@js/editor/types/editorTypes";
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import CommentThread from "@schoolboxLearningMomentEvidence/comment-thread.vue";
import EvidenceTags from "@schoolboxLearningMomentEvidence/evidence-tags.vue";
import MfActions from "./mf-actions.vue";
import MfEvidence from "./mf-evidence.vue";
import MfSources from "./mf-sources.vue";
import MfModal from "./mf-modal.vue";
import { useMomentFormStore } from "./store/momentFormStore";
import type { MomentFormFolder } from "./store/momentFormStore";

interface Props {
    /**
     * Autosave is available if there's a draft route
     * and this isn't a create form for multiple dudes at the same time
     *
     * After the initial POST, we end up choosing what to do based on the response we get,
     * either sending the user somewhere else, or updating the form to be an edit form
     */
    hasAutosave: boolean;
    returnUrl: string;
    /**
     * If this is a create form, it's populated with the post routes to use
     * as props.moment will be an empty object and will not reflect
     * the correct routes (doesn't support multiple users, those are puts, etc)
     *
     * null if editing an existing moment
     */
    createRoutes: null | LearningMoment["_links"];
    /**
     * The current learning moment that is being edited
     * It may not have a meaningful ID yet if props.isCreate is true
     */
    moment: LearningMoment;
    editorTemplates: Template[];
    endpointGetEvidenceTags: string;
    /** Curriculum Nodes */
    curriculumNodes: Node[];
    endpointCurriculumNodes: string | null;
    /** Classes and Groups */
    folders: MomentFormFolder[];
    selectedFolders: number[];
    /* A special label for the Publish button that changes to Approve
     * in exactly one workflow
     */
    publishLabel: string;
}

const props = defineProps<Props>();
const mfStore = useMomentFormStore();

window.Vue.onMounted(() => {
    mfStore.init(
        props.hasAutosave,
        props.returnUrl,
        props.createRoutes,
        props.moment,
        props.folders,
        props.selectedFolders,
    );

    // Has the user asked for folders to be selected in the URL? i.e. come from a homepage component
    const urlParams = new URLSearchParams(window.location.search);
    const folderIds = urlParams.get("folderId")?.split(",") ?? [];

    if (folderIds.length > 0) {
        mfStore.selectedFolders = [
            ...new Set([
                ...mfStore.selectedFolders,
                ...folderIds.map((id) => parseInt(id, 10)).filter((id) => !Number.isNaN(id)),
            ]),
        ];
    }
});
</script>

<template>
    <div class="actions-small-1 row">
        <div class="list-item">
            <div class="small-12 column">
                <slot name="heading"></slot>
            </div>
        </div>

        <template v-if="mfStore.moment && mfStore.moment._links.delete">
            <nav>
                <sbx-pop-out content-id="mf-pop-out"></sbx-pop-out>
            </nav>
            <ul id="mf-pop-out" class="f-dropdown" data-dropdown-content>
                <li>
                    <a
                        class="icon-delete"
                        :href="mfStore.moment._links.delete"
                        @click.prevent="() => mfStore.deleteMoment()"
                    >
                        Delete
                    </a>
                </li>
            </ul>
        </template>
    </div>

    <div
        v-if="
            mfStore.moment !== null &&
            mfStore.moment.discussionThread !== null &&
            mfStore.moment.discussionThread.children.metadata.count
        "
        class="row"
    >
        <div class="small-12 island">
            <section class="moment-comments">
                <comment-thread
                    :thread="mfStore.moment.discussionThread"
                    :create-thread-endpoint="mfStore.moment._links.createThread || undefined"
                    :can-comment="false"
                />
            </section>
        </div>
    </div>

    <div v-if="mfStore.moment !== null" class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns l-flex-col l-gap-m">
                        <mf-evidence :editor-templates="props.editorTemplates" />

                        <label>
                            Tags
                            <evidence-tags
                                v-model:tags="mfStore.moment.tags"
                                :endpoint-get-evidence-tags="props.endpointGetEvidenceTags"
                            />
                        </label>

                        <label v-if="props.endpointCurriculumNodes">
                            Curriculum Codes
                            <curriculum-selector
                                :is-read-only="false"
                                :selected-nodes="props.curriculumNodes"
                                :data-endpoint="props.endpointCurriculumNodes"
                                :include-children="true"
                                :has-tabs="false"
                            />
                        </label>
                    </div>
                </fieldset>

                <mf-sources />
            </section>
            <div v-if="mfStore.showInvalidEvidenceError">
                Evidence must be added to the learning moment before the form can be submitted
            </div>
            <mf-actions :publish-label="props.publishLabel" />
        </div>
    </div>
    <mf-modal />
</template>

<style scoped lang="scss">
// We're controlling margins here, so override it from the selectize plugin styles
:deep(.selectize-control) {
    margin-bottom: 0;
}
</style>
