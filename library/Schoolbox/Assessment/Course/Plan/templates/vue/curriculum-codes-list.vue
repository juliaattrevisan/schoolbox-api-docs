<script setup lang="ts">
/**
 * A component that displays a list of curriculum codes
 */
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    curriculumCodes: Node[];
}

const props = defineProps<Props>();
</script>

<template>
    <ul v-if="props.curriculumCodes.length > 0" class="information-list">
        <template v-for="node in props.curriculumCodes" :key="node.id">
            <li v-if="node.enabled">
                <div class="content">
                    <template v-if="node.code">
                        [<a :href="'#' + node.id" title="View curriculum node details" data-display-node>{{
                            node.code
                        }}</a
                        >]
                    </template>
                    {{ node.name }}

                    <curriculum-codes-list
                        v-if="node.children !== null"
                        :curriculum-codes="node.children"
                    ></curriculum-codes-list>
                </div>
            </li>
        </template>
    </ul>
</template>

<style scoped>
.content {
    overflow: hidden;
    padding-bottom: 5px;
    padding-top: 5px;
    text-overflow: ellipsis;
    text-wrap: nowrap;
}
</style>
