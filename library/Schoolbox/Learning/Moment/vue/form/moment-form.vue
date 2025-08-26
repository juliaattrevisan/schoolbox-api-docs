<script setup lang="ts">
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import EvidenceTags from "@schoolboxLearningMomentEvidence/evidence-tags.vue";
import MfActions from "./mf-actions.vue";
import MfEvidence from "./mf-evidence.vue";
import MfSources from "./mf-sources.vue";
import { useMomentFormStore } from "./store/momentFormStore";

interface Props {
    isCreate: boolean;
    /**
     * The current learning moment that is being edited
     * It may not have a meaningful ID yet if props.isCreate is true
     */
    moment: LearningMoment;
    endpointGetEvidenceTags: string;
    /** Curriculum Nodes */
    curriculumNodes: Node[];
    endpointCurriculumNodes: string;
    /** Classes and Groups */
    folders: { id: number; title: string }[];
    selectedFolders: number[];
}

const props = defineProps<Props>();
const mfStore = useMomentFormStore();

window.Vue.onMounted(() => {
    mfStore.init(props.isCreate, props.moment, props.folders, props.selectedFolders);
});
</script>

<template>
    <div v-if="mfStore.moment !== null" class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <mf-evidence />

                        <label>
                            Tags
                            <evidence-tags
                                v-model:tags="mfStore.moment.tags"
                                :endpoint-get-evidence-tags="props.endpointGetEvidenceTags"
                            />
                        </label>

                        <label>
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

                    <mf-sources />
                </fieldset>
            </section>
            <mf-actions :moment="moment" />
        </div>
    </div>
</template>

<style scoped></style>
