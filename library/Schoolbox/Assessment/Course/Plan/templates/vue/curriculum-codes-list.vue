<script setup lang="ts">
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    curriculumCodes: Node[];
}

const props = defineProps<Props>();
</script>

<template>
    <ul v-if="props.curriculumCodes.length > 0" class="information-list">
        <li v-for="node in props.curriculumCodes" :key="node.id">
            <div class="content">
                <template v-if="node.code">
                    [<a :href="'#' + node.id" title="View curriculum node details" data-display-node>{{ node.code }}</a
                    >]
                </template>
                {{ node.name }}

                <curriculum-codes-list
                    v-if="node.children !== null"
                    :curriculum-codes="node.children"
                ></curriculum-codes-list>
            </div>
        </li>
    </ul>
</template>

<style scoped>
.content {
    overflow: hidden;
    padding-top: 5px;
    padding-bottom: 5px;
    text-wrap: nowrap;
    text-overflow: ellipsis;
}
</style>
