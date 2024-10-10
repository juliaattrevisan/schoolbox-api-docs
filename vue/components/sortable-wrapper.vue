<script setup lang="ts" generic="T">
/**
 * A wrapper component for the sortablejs library
 * Modified from https://github.com/MaxLeiter/sortablejs-vue3
 */
import type { Ref } from "vue";
import type { SortableOptions } from "sortablejs";
import Sortable from "sortablejs";
import type { AutoScrollOptions } from "sortablejs/plugins";

type SortableOptionsProp = Omit<
    SortableOptions | AutoScrollOptions,
    | "onUnchoose"
    | "onChoose"
    | "onStart"
    | "onEnd"
    | "onAdd"
    | "onUpdate"
    | "onSort"
    | "onRemove"
    | "onFilter"
    | "onMove"
    | "onClone"
    | "onChange"
>;

type ExposedProps = {
    containerRef: Ref<HTMLDivElement | null>;
    sortable: Ref<Sortable | null>;
    isDragging: Ref<boolean>;
};

interface Props {
    /** All SortableJS options are supported; events are handled by the `defineEmits` call */
    options?: SortableOptionsProp | null;
    /** Your list of items */
    list: T[];
    /** A unique key to identify each item in the list */
    itemKey: (item: T) => string | number | symbol;
    /** The element type to render as. */
    tag?: string;
    /**
     * Whether items should transition when they are entirely added or removed from the list
     * Note: it's contained in here instead of up a level since the <slot> would otherwise break a <Transition>
     */
    transitionKey?: string;
    transitionEnter?: (el: Element, done: () => void) => void;
    transitionLeave?: (el: Element, done: () => void) => void;
}

const props = withDefaults(defineProps<Props>(), {
    options: null,
    tag: "div",
    transitionKey: undefined,
    transitionEnter: undefined,
    transitionLeave: undefined,
});

const emit = defineEmits<{
    (eventName: "choose", evt: Sortable.SortableEvent): void;
    (eventName: "unchoose", evt: Sortable.SortableEvent): void;
    (eventName: "start", evt: Sortable.SortableEvent): void;
    (eventName: "end", evt: Sortable.SortableEvent): void;
    (eventName: "add", evt: Sortable.SortableEvent): void;
    (eventName: "update", evt: Sortable.SortableEvent): void;
    (eventName: "sort", evt: Sortable.SortableEvent): void;
    (eventName: "remove", evt: Sortable.SortableEvent): void;
    (eventName: "filter", evt: Sortable.SortableEvent): void;
    (eventName: "move", evt: Sortable.MoveEvent, originalEvent: Event): void;
    (eventName: "clone", evt: Sortable.SortableEvent): void;
    (eventName: "change", evt: Sortable.SortableEvent): void;
}>();

const isDragging = window.Vue.ref(false);
const containerRef = window.Vue.ref<HTMLElement | null>(null);
const sortable = window.Vue.ref<Sortable | null>(null);

defineExpose({
    containerRef,
    sortable,
    isDragging,
} as ExposedProps);

const draggingIndex = window.Vue.ref<number | null>(null);

window.Vue.watch(containerRef, (newDraggable) => {
    if (newDraggable) {
        sortable.value = new Sortable(newDraggable, {
            ...props.options,
            onChoose: (event): void => {
                draggingIndex.value = event.oldIndex ?? null;
                emit("choose", event);
            },
            onUnchoose: (event): void => emit("unchoose", event),
            onStart: (event): void => {
                // deselect any text that may have been selected
                window.getSelection()?.removeAllRanges();

                isDragging.value = true;
                emit("start", event);
            },
            onEnd: (event): void => {
                // This is a hack to move the event to the end of the event queue.
                // cf this issue: https://github.com/SortableJS/Sortable/issues/1184
                setTimeout(() => {
                    isDragging.value = false;
                    emit("end", event);
                });
            },
            onAdd: (event): void => emit("add", event),
            onUpdate: (event): void => emit("update", event),
            onSort: (event): void => emit("sort", event),
            onRemove: (event): void => emit("remove", event),
            onFilter: (event): void => emit("filter", event),
            onMove: (event, originalEvent): void => emit("move", event, originalEvent),
            onClone: (event): void => emit("clone", event),
            onChange: (event): void => emit("change", event),
        });
    }
});

window.Vue.watch(
    () => props.options,
    (options) => {
        if (options && sortable?.value) {
            Object.keys(options).forEach((property) => {
                if (Object.prototype.hasOwnProperty.call(options, property)) {
                    if (sortable.value === null) return;
                    sortable.value.option(
                        property as keyof SortableOptionsProp,
                        options[property as keyof SortableOptionsProp],
                    );
                }
            });
        }
    },
);

window.Vue.onUnmounted(() => {
    if (sortable.value) {
        sortable.value.destroy();
        containerRef.value = null;
        sortable.value = null;
    }
});
</script>

<template>
    <component :is="props.tag" ref="containerRef">
        <template v-if="props.transitionKey">
            <TransitionGroup :key="props.transitionKey" @enter="props.transitionEnter" @leave="props.transitionLeave">
                <slot
                    v-for="(item, index) of props.list"
                    :key="props.itemKey(item)"
                    :element="item"
                    :index="index"
                    :is-dragging="isDragging && draggingIndex === index"
                    name="item"
                ></slot>
            </TransitionGroup>
        </template>
        <template v-else>
            <slot
                v-for="(item, index) of props.list"
                :key="props.itemKey(item)"
                :element="item"
                :index="index"
                :is-dragging="isDragging && draggingIndex === index"
                name="item"
            ></slot>
        </template>
    </component>
</template>
