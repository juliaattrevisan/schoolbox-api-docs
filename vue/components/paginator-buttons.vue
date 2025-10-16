<script setup lang="ts">
/**
 * To display pagination controls for an API endpoint
 * This is pretty much just templates/core/sub/paginator.twig, but for use in Vue
 */

interface Props {
    pageCount: number;
    current: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: "page-change", page: number): void;
}>();

/**
 * To change the currently selected page
 * @param page - The new page number
 */
function changePage(page: number): void {
    if (page < 1 || page > props.pageCount || page === props.current) {
        return;
    }

    emit("page-change", page);
}
</script>

<template>
    <ul v-if="props.pageCount > 1" class="pagination">
        <li class="arrow" :class="{ unavailable: props.current === 1 }">
            <button title="First Page" :disabled="props.current === 1" @click="changePage(1)">«</button>
        </li>
        <li class="arrow" :class="{ unavailable: props.current === 1 }">
            <button title="Previous Page" :disabled="props.current === 1" @click="changePage(props.current - 1)">
                ‹
            </button>
        </li>
        <li v-for="n in props.pageCount" :key="n" :class="{ active: n === props.current }">
            <a :title="`Page ${n}`" @click.prevent="changePage(n)">{{ n }}</a>
        </li>
        <li class="arrow" :class="{ unavailable: props.current === props.pageCount }">
            <button
                title="Next Page"
                :disabled="props.current === props.pageCount"
                @click="changePage(props.current + 1)"
            >
                ›
            </button>
        </li>
        <li class="arrow" :class="{ unavailable: props.current === props.pageCount }">
            <button
                title="Last Page"
                :disabled="props.current === props.pageCount"
                @click="emit('page-change', props.pageCount)"
            >
                »
            </button>
        </li>
    </ul>
</template>
