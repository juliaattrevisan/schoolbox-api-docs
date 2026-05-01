<script setup lang="ts">
/**
 * Button to apply a result set to the rubric grid.
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { ResultSet } from "./types";

const id = window.Vue.useId(); // there'll be multiple of these, avoid conflicts

interface Props {
    resultSets: ResultSet[];
}
const props = defineProps<Props>();
const emit = defineEmits<{
    applyResultSet: [ResultSet];
}>();

const showModal = window.Vue.ref(false);
const selectedSet = window.Vue.ref<ResultSet | null>(null);
</script>

<template>
    <button class="button l-mar-m--bottom" type="button" @click="showModal = true">Apply Result Set</button>

    <basic-modal v-model:is-open="showModal" has-background-click>
        <h2>Apply Result Set</h2>
        <p>Choose a standard result set to apply to this {{ locale.rubricCriterion.singular() }} grid.</p>
        <div class="alert-box warning">
            <strong>Warning:</strong> this is a destructive action. Any existing level and descriptor data will be
            replaced with the chosen result set.
        </div>

        <h3>Available Result Sets</h3>
        <ul class="option-list">
            <li v-for="resultSet in props.resultSets" :key="resultSet.id">
                <input
                    :id="`rs-${resultSet.id}-${id}`"
                    v-model="selectedSet"
                    type="radio"
                    :name="`resultset-${id}`"
                    :value="resultSet"
                />
                <label :for="`rs-${resultSet.id}-${id}`" class="radio-label">
                    {{ resultSet.results.map((r) => r.label).join(", ") }}
                </label>
            </li>
        </ul>
        <ul class="flex-list buttons">
            <li>
                <button
                    type="button"
                    class="submit button"
                    :disabled="!selectedSet || undefined"
                    @click="
                        selectedSet && emit('applyResultSet', selectedSet);
                        selectedSet = null;
                        showModal = false;
                    "
                >
                    Apply
                </button>
            </li>
            <li><button type="button" class="button" @click="showModal = false">Cancel</button></li>
        </ul>
    </basic-modal>
</template>
