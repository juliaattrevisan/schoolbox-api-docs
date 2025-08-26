<script setup lang="ts">
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";
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
}

const props = defineProps<Props>();
const mfStore = useMomentFormStore();

window.Vue.onMounted(() => {
    mfStore.init(props.isCreate, props.moment);
});
</script>

<template>
    <div v-if="mfStore.moment" class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <mf-evidence />

                        <!-- TAGS -->

                        <!-- CLASSES/GROUPS -->
                    </div>
                </fieldset>
            </section>
            <mf-sources />
            <mf-actions :moment="moment" />
        </div>
    </div>
</template>

<style scoped></style>
