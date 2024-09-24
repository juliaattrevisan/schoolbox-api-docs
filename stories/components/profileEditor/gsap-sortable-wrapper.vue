<script setup lang="ts" generic="T">
import { gsap, Draggable, Flip } from "gsap/all";
import {
    insertElementAfter,
    insertElementBefore,
    setStylesOnElement,
    getClosestElement,
    isElementAboveOtherElement,
    isElementBelowOtherElement,
} from "@js/util/domUtils";

const ANIMATE_SPEED = 0.15; // the speed of animations
const MIN_MOVEMENT = 50; // minimum amount of movement before we can FLIP again

const COMPONENT_DRAG_HANDLE = "[data-pe-field-drag-handle]";
const COMPONENT_SELECTOR = "[data-sortable-item]";
let isScrollingTimeout: number | false = false; // to prevent sorting when moving a sortable up/down the window
let isScrolling: boolean = false;

gsap.registerPlugin(Draggable, Flip);

interface Props {
    list: T[];
    itemKey: (item: T) => string | number;
    tag?: string;
}

const props = withDefaults(defineProps<Props>(), {
    tag: "div",
});

const emit = defineEmits<{
    (event: "reorder", newList: T[]): void;
}>();

const containerRef = window.Vue.ref<HTMLElement | null>(null);
const draggableInstances = window.Vue.ref<Draggable[]>([]);
let activeComponent: HTMLElement | null = null;
let referenceElement: HTMLElement | null = null;
let placeholderElement: HTMLElement | null = null;
let activeElement: HTMLElement | null = null;
let startDragPoint = { left: 0, top: 0 };
let isFlipping: boolean = false;
let hasSortChange = false; // to know whether to update the DB with sort change
let isChangingSize: boolean = false;

/**
 * To track drag movement
 * @returns - The current drag point
 */
function updateDragPoint(): { left: number; top: number } {
    if (!activeComponent) return { left: 0, top: 0 };
    return {
        left: activeComponent.getBoundingClientRect().left,
        top: activeComponent.getBoundingClientRect().top + window.scrollY,
    };
}

/**
 * Don't do sorting while moving up or down the window due to dragging at the window edges
 */
function onScroll(): void {
    isScrolling = true;
    if (isScrollingTimeout) window.clearTimeout(isScrollingTimeout); // Clear our timeout throughout the scroll
    isScrollingTimeout = window.setTimeout(() => {
        // Set a timeout to run after scrolling ends
        isScrolling = false;
    }, 66);
}

/**
 * Prep the document to handle dragging
 */
function updateDocumentOnDragStart(): void {
    document.body.style.overflowX = "hidden"; // avoid horizontal scroll bars when a sortable gets moved beyond the window edges
    document.documentElement.style.scrollBehavior = "auto"; // disable smooth scroll as it can jar while moving elements around
    document.querySelectorAll("[data-collapsable]").forEach((element) => {
        if (element instanceof HTMLElement) element.style.pointerEvents = "none";
    }); // prevent any mouse interactions interferring while dragging
    document.addEventListener("scroll", onScroll); // prevent sorting check while moving up/down window
}

/**
 * Return the document back to its pre onDragStart behaviour
 */
function updateDocumentOnDragEnd(): void {
    document.body.style.overflowX = "auto"; // was hidden to avoid horizontal scroll bars
    document.documentElement.style.scrollBehavior = "smooth"; // was auto to avoid jarring behaviour
    document.querySelectorAll("[data-collapsable]").forEach((element) => {
        if (element instanceof HTMLElement) element.style.pointerEvents = "auto";
    }); // re-enable mouse interactions
    document.removeEventListener("scroll", onScroll); // no need to listen for scrolling until next drag
    window.dispatchEvent(new Event("resize")); // for components to respond to - for example swiper.js image gallerys to update to display correctly
}

/**
 * Create a clone of the __activeComponent being dragged to use as a placeholder
 * and to keep it in position as the __activeComponent will be pulled out of context
 * @returns - The placeholder element
 */
function getReferenceElement(): HTMLElement | null {
    if (!activeComponent) return null;
    const refEl = activeComponent.cloneNode(true) as HTMLElement;

    const collapsableContent = refEl.querySelector("[data-collapsable]");
    if (collapsableContent) {
        collapsableContent.remove();
    } // just use the top bar as reference, so remove all component controls that can be expanded

    refEl.querySelector('[data-component-panel="settings"]')?.remove();
    refEl.querySelector('[data-component-panel="delete"]')?.remove();
    refEl.style.opacity = "0.5"; // displayed as reference where the __activeComponent is currently placed
    insertElementBefore(activeComponent, refEl);
    return refEl;
}

/**
 * Disable all others until current drag is finished to avoid rapid fire / double click issues
 */
function disableAllOtherDraggables(): void {
    draggableInstances.value.forEach((draggable) => {
        // to avoid disabling the current
        if (activeComponent && draggable.target.id !== activeComponent.id) {
            draggable.disable();
        }
    });
}

/**
 * Run through each Draggable instance and enable/disable
 * @param isEnabled - Whether to enable or disable the Draggable instances
 */
function updateDraggablesStatus(isEnabled: boolean): void {
    draggableInstances.value.forEach((draggable) => (isEnabled ? draggable.enable() : draggable.disable()));
}

/**
 * With the __referenceElement in place, the __activeComponent is pulled out
 * so it doesn't impact our ability to sort through the items and can go into any container
 */
function extractActiveComponent(): void {
    if (!activeComponent || !referenceElement) return;

    document.body.appendChild(activeComponent);
    activeComponent.removeAttribute("data-component"); // so there's no position checks when dragging, as the placeholder is there instead
    setStylesOnElement(activeComponent, {
        // Update position and styles to match now that the __activeComponent has been moved out of context
        position: "absolute",
        top: `${referenceElement.getBoundingClientRect().top + window.scrollY}px`,
        left: `${referenceElement.getBoundingClientRect().left - 16}px`, // subtracting 16 to deal with 1rem padding-left that's applied
        width: `${referenceElement.getBoundingClientRect().width + 32}px`, // and adding 32 to also deal with 1rem padding-left, padding-right that's applied each side
    });
}

/**
 * To be able to sort elements and snap back to correct position
 * When sorting and snapping back, these points are used to animate the components movements
 */
function updateSortableElements(): void {
    draggableInstances.value.forEach((draggable) => {
        const component = draggable.target as HTMLElement;
        component.dataset.originalLeft = component.getBoundingClientRect().left.toString();
        component.dataset.originalTop = component.getBoundingClientRect().top + window.scrollY.toString();
        component.dataset.leftDifference = "0";
        component.dataset.topDifference = "0";
        component.removeAttribute("data-flip-id"); // sometimes FLIP currently leaves this behind
    });
}

/**
 * Get a list of all the containers of the current components
 * @returns - The list of containers
 */
function getComponentContainers(): HTMLElement[] {
    const allTargets = Array.from(document.querySelectorAll(COMPONENT_SELECTOR)) as HTMLElement[];
    const containers = allTargets
        .map((target) => target.closest("[data-sortable-container]"))
        .filter((container) => container instanceof HTMLElement);

    return Array.from(new Set(containers)); // dedupe
}

/**
 * Flip currently sometimes leaves a left over element while dragging between elements. It's cleared on mouse release, but we need
 * it to be cleared sooner as we need to check for whether we need to add place holders after a component shift
 */
function clearFlipElements(): void {
    getComponentContainers().forEach((container) => {
        if (container.children.length === 1 && !container.children[0].id) {
            // there's the flip element here, so we should clear it out
            container.removeChild(container.children[0]);
        }
    });
}

/**
 * Animate the sorting position change
 * @param state - The current flip state
 */
function animateFlip(state: Flip.FlipState): void {
    Flip.from(state, {
        duration: ANIMATE_SPEED,
        onComplete: () => {
            isFlipping = false;
            onDrag(); // check after Flip to see if another is required
        },
    });
}

/**
 * Use Flip to shift the elements around, recording the difference in shift
 * for when the dragged element is released
 * Refer to https://greensock.com/docs/v3/Plugins/Flip for more info on Flip
 * @param closestElement - The element nearest to the dragged element
 * @param isShiftingDown - Whether the dragged element is moving down
 */
function updateActiveComponentPosition(closestElement: HTMLElement, isShiftingDown: boolean): void {
    if (!activeComponent || !referenceElement) return;

    hasSortChange = true;
    isFlipping = true;
    startDragPoint = updateDragPoint(); // reset the start point to consider MIN_MOVEMENT etc again before another shift

    const allTargets = Array.from(document.querySelectorAll(COMPONENT_SELECTOR)) as HTMLElement[];
    const state = Flip.getState(allTargets); // record position of elements before we move them around

    if (isShiftingDown) insertElementAfter(closestElement, referenceElement);
    else insertElementBefore(closestElement, referenceElement); // insert into the appropriate spot depending on drag position

    clearFlipElements(); // check for flip bug

    // document.dispatchEvent(new Event(UpdateMasonryEvents.EXISTING_COMPONENT_SORT_UPDATE));

    activeComponent.dataset.leftDifference = (
        referenceElement.getBoundingClientRect().left - parseInt(referenceElement.dataset.originalLeft ?? "0", 10)
    ).toString(); // so can animate the dragged element to this new position on mouse release

    activeComponent.dataset.topDifference = (
        referenceElement.getBoundingClientRect().top +
        window.scrollY -
        parseInt(referenceElement.dataset.originalTop ?? "0", 10)
    ).toString();

    gsap.to(activeComponent, {
        width: referenceElement.parentElement?.offsetWidth,
        duration: ANIMATE_SPEED,
    }); // parentElement as the placeHolder may not have fully shifted into place before mouse release})

    animateFlip(state);
}

/**
 * Sub in a placeholder to use in place of the element being dragged,
 * which gets pulled out so it can go into any other parent container
 * that contains the COMPONENT_SELECTOR
 * @param draggableInstance - The instance of the draggable element
 */
function onDragStart(draggableInstance: Draggable): void {
    activeComponent = draggableInstance.target as HTMLElement;
    referenceElement = getReferenceElement();
    startDragPoint = updateDragPoint(); // to track when we can allow sorting check based on MIN_MOVEMENT
    updateDocumentOnDragStart();
    disableAllOtherDraggables();
    extractActiveComponent();
    updateSortableElements(); // ensure we have the most up to date positions

    console.log("onDragStart", activeComponent, referenceElement);
}

/**
 * Don't be checking while have hardly dragged, while the window is scrolling or
 * while the __activeComponent is still shrinking in collapseActiveComponentContent()
 * @returns - Whether we can check for a change in position
 */
function canCheckForChange(): boolean {
    if (!activeComponent) return false;

    const currentDragPoint = {
        left: activeComponent.getBoundingClientRect().left,
        top: activeComponent.getBoundingClientRect().top + window.scrollY,
    };
    const distance = Math.sqrt(
        (startDragPoint.left - currentDragPoint.left) ** 2 + (startDragPoint.top - currentDragPoint.top) ** 2,
    );
    return distance > MIN_MOVEMENT && !isScrolling && !isChangingSize && !isFlipping;
}

/**
 * Constantly check for the nearest other sortable and update position
 * based on whether a move is valid
 */
function onDrag(): void {
    if (!activeComponent) return;

    if (canCheckForChange()) {
        const sortablesToCheckList = (
            Array.from(document.querySelectorAll(COMPONENT_SELECTOR)) as HTMLElement[]
        ).filter((element) => element !== activeComponent);
        const closestElement = getClosestElement(activeComponent, sortablesToCheckList); // the element nearest to the dragged element
        if (closestElement !== activeComponent) {
            console.log(activeComponent, closestElement, sortablesToCheckList);
            // no need to FLIP if the closest element is still the __activeComponent
            if (isElementAboveOtherElement(activeComponent, closestElement)) {
                console.log("ABOVE");
                // otherwise determine movement direction, up or down into the container
                updateActiveComponentPosition(closestElement, true);
            } else if (isElementBelowOtherElement(activeComponent, closestElement)) {
                console.log("BELOW");
                updateActiveComponentPosition(closestElement, false);
            }
        }
    }
}

/**
 * Tidy up once the element has snapped back
 * setting up for another drag, returning various variables back to there pre dragging state etc
 */
function onSnapBackActiveComponentComplete(): void {
    if (!activeComponent || !referenceElement) return;

    gsap.set(activeComponent, { x: 0, y: 0, width: "100%" }); // reset the animation properties for another animate
    activeComponent.style.position = "static"; // was absolute, now sitting as it should
    activeComponent.style.transform = "none"; // was translate3d(0px, 0px, 0px), changed this to none to fix the ck editor was opening behind the home page components
    activeComponent.setAttribute("data-component", ""); // so it can be dragged again
    // removeActiveComponentComputedStyles(); // ensure the defined colours are respected

    insertElementAfter(referenceElement, activeComponent); // the element was taken out, so put it back where it now belongs
    referenceElement.remove(); // and now the placeholder can be pulled out
}

/**
 * Move the component back to its original spot or its new updated by sort spot
 */
function snapBackActiveComponent(): void {
    if (!activeComponent || !referenceElement) return;

    gsap.to(activeComponent, {
        x: 0 + parseInt(activeComponent.dataset.leftDifference ?? "0", 10), // to accommodate for potential position changes that have occured
        y: 0 + parseInt(activeComponent.dataset.topDifference ?? "0", 10),
        width: referenceElement.parentElement?.offsetWidth, // parentElement as the placeHolder may not have fully shifted into place before mouse release
        duration: ANIMATE_SPEED,
        overwrite: true,
        onComplete: () => onSnapBackActiveComponentComplete(),
    });
}

/**
 * When the drag has ended
 */
function onDragEnd(): void {
    // document.dispatchEvent(new Event(UpdateMasonryEvents.PROCESSING_START));
    // document.dispatchEvent(new Event(UpdateMasonryEvents.EXISTING_COMPONENT_SORT_END));
    snapBackActiveComponent();
}

/**
 * To enable Dragging functionality on a component
 * Refer to https://greensock.com/docs/v3/Plugins/Draggable for further info
 * @param component - The component to enable dragging on
 * @returns - draggableInstance
 */
function addNewDraggable(component: HTMLElement): Draggable {
    const componentTrigger = component.querySelector(COMPONENT_DRAG_HANDLE) ?? undefined;

    console.log("new component", component, componentTrigger);

    if (componentTrigger) {
        componentTrigger.addEventListener("click", (event) => event.preventDefault());
        componentTrigger.addEventListener("dblclick", (event) => event.preventDefault());
    }

    const draggableInstance = Draggable.create(component, {
        type: "x,y",
        trigger: componentTrigger,
        autoScroll: 1,
        onDragStart: () => {
            onDragStart(draggableInstance);
        },
        onDrag: () => onDrag(),
        onDragEnd: () => onDragEnd(),
    })[0];

    return draggableInstance;
}

/**
 * When the list of components changes, update the draggable instances
 */
window.Vue.onMounted(() => {
    window.Vue.watch(
        props.list,
        (newList) => {
            draggableInstances.value.forEach((instance) => instance.kill());
            const newDraggables = containerRef.value?.querySelectorAll("[data-sortable-item]");
            console.log("newList", newList, newDraggables);
            if (newDraggables) {
                draggableInstances.value = Array.from(newDraggables).map((component) =>
                    addNewDraggable(component as HTMLElement),
                );
            }
        },
        { immediate: true },
    );
});

window.Vue.onUnmounted(() => {
    draggableInstances.value.forEach((instance) => instance.kill());
});

// const setupDraggables = () => {
//     if (!containerRef.value) return;

//     const items = containerRef.value.querySelectorAll("[data-sortable-item]");

//     draggableInstances.value = Draggable.create(items, {
//         type: "x,y",
//         onDragStart: function () {
//             activeElement = this.target as HTMLElement;
//             placeholderElement = activeElement.cloneNode(true) as HTMLElement;
//             activeElement.parentNode?.insertBefore(placeholderElement, activeElement);
//             document.body.appendChild(activeElement);
//             activeElement.style.position = "absolute";
//             activeElement.style.zIndex = "1000";
//             activeElement.style.top = `${placeholderElement.getBoundingClientRect().top + window.scrollY}px`;
//             activeElement.style.left = `${placeholderElement.getBoundingClientRect().left}px`;
//             activeElement.style.width = `${placeholderElement.getBoundingClientRect().width}px`;
//             startDragPoint = updateDragPoint();
//         },
//         onDrag,
//         onDragEnd,
//     });
// };

// const onDrag = () => {
//     if (!activeElement || !placeholderElement) return;

//     const currentPoint = updateDragPoint();
//     const distance = Math.sqrt(
//         Math.pow(startDragPoint.left - currentPoint.left, 2) + Math.pow(startDragPoint.top - currentPoint.top, 2),
//     );

//     if (distance > minMovement && !isFlipping) {
//         const items = containerRef.value?.querySelectorAll("[data-sortable-item]");
//         if (items) {
//             const closestElement = Array.from(items).find(
//                 (item) => item !== activeElement && item !== placeholderElement,
//             );
//             if (closestElement) {
//                 const isAbove = activeElement.getBoundingClientRect().top < closestElement.getBoundingClientRect().top;
//                 Flip.getState(items);
//                 if (isAbove) {
//                     placeholderElement.parentNode?.insertBefore(placeholderElement, closestElement);
//                 } else {
//                     placeholderElement.parentNode?.insertBefore(placeholderElement, closestElement.nextSibling);
//                 }
//                 isFlipping = true;
//                 Flip.from(items, {
//                     duration: 0.5,
//                     onComplete: () => (isFlipping = false),
//                 });
//             }
//         }
//     }
// };

// const onDragEnd = () => {
//     if (!activeElement || !placeholderElement) return;

//     gsap.to(activeElement, {
//         x: 0,
//         y: 0,
//         width: "100%",
//         onComplete: () => {
//             placeholderElement?.parentNode?.replaceChild(activeElement, placeholderElement);
//             activeElement!.style.position = "static";
//             activeElement!.style.zIndex = "";
//             emitReorder();
//             placeholderElement = null;
//             activeElement = null;
//         },
//     });
// };

// const emitReorder = () => {
//     const reorderedItems = Array.from(containerRef.value?.children || []).map((el) => {
//         return props.list.find((item) => `${props.itemKey(item)}` === el.getAttribute("data-key"));
//     });
//     emit("reorder", reorderedItems);
// };

window.Vue.onBeforeUnmount(() => {
    draggableInstances.value.forEach((instance) => instance.kill());
});
</script>

<template>
    <component :is="props.tag" ref="containerRef">
        <slot v-for="(item, index) in props.list" :key="props.itemKey(item)" :element="item" :index="index" name="item">
        </slot>
    </component>
</template>
