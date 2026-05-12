<script setup lang="ts">
import type { Dayjs } from "dayjs";
import { keysOf } from "@js/util/arrayUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { Template } from "@js/editor/types/editorTypes";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import AwardDetailModal from "@schoolboxBadge/Award/vue/award-detail-modal.vue";
import BadgeImage from "@schoolboxBadge/vue/badge-image.vue";
import type { BadgeAward } from "@schoolboxBadge/templates/types/BadgeAward";
import type { DueWorkFeedback, QuizFeedback } from "@schoolboxLibrary/Learning/templates/types/activityTypes";
import userProfileConstants from "../../vue/user-profile/constants";
import CommentThread from "./comment-thread.vue";
import MomentEvidenceGallery from "./moment-evidence-gallery.vue";
import { EvidenceStatus, evidenceStatusLabels } from "./evidenceTypes";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    /** Whether to show learning moments within the feed */
    showLearningMoments: boolean;
    /** Whether to show goals within the feed */
    showGoals: boolean;
    /** Whether to show achievements within the feed */
    showAchievements: boolean;
    /** Whether to show feedback within the feed */
    showFeedback: boolean;
    /** Learning moment moderation requirement */
    moderationRequired: boolean;
    /** Whether parents can create learning moments */
    momentParentCreate: boolean;
    /** Feedback editor templates to use in the comment field */
    editorTemplates?: Template[];
    /** Whether to use tokens in the comment editor */
    editorHasTokens?: boolean;
    /** The student that this feed is for */
    student: {
        id: number;
        givenName: string;
    };
    /** Whether the current user can comment on learning moments */
    canComment: boolean;
    /** Relevant routes */
    routes: {
        getCreateEvidenceForm: string | null;
        getSetAsGoalForm: string | null;
        getSetAsGoalForSelfForm: string | null;
        getAwardAsAchievementForm: string | null;
        getFeed: string | null;
    };
}

const props = defineProps<Props>();
const { dateDisplayFormats, schoolboxUser: currentUser } = window;

window.Vue.provide("editorTemplates", props.editorTemplates);
window.Vue.provide("editorHasTokens", props.editorHasTokens);

enum FeedDiscriminator {
    /**
     * Discriminator value for identifying that a retrieved result points to an
     * learning moment record.
     */
    LEARNING_MOMENT = "moment",
    /**
     * Discriminator value for identifying that a retrieved result points to a
     * badge award.
     */
    BADGE_AWARD = "badgeAward",
    /**
     * Discriminator value for identifying that a retrieved result points to a
     * duework feedback.
     */
    DUE_WORK_FEEDBACK = "dueWorkFeedback",
    /**
     * Discriminator value for identifying that a retrieved result points to a
     * quiz feedback.
     */
    QUIZ_FEEDBACK = "quizFeedback",
}

type FeedItem = {
    date: string;
} & (
    | { discriminator: FeedDiscriminator.LEARNING_MOMENT; object: LearningMoment }
    | { discriminator: FeedDiscriminator.BADGE_AWARD; object: BadgeAward }
    | { discriminator: FeedDiscriminator.DUE_WORK_FEEDBACK; object: DueWorkFeedback }
    | { discriminator: FeedDiscriminator.QUIZ_FEEDBACK; object: QuizFeedback }
);

const data = window.Vue.ref<FeedItem[]>([]);
const metadata = window.Vue.reactive<Metadata>({
    count: 0,
    cursor: {
        current: null,
        next: null,
    },
});

const showFeed = window.Vue.ref(true);
const showSkeletonLoader = window.Vue.ref(true);
const currentlyDeletingItem = window.Vue.ref<FeedItem | null>(null);
const isDeleteModalOpen = window.Vue.ref(false);

/** the selected curriculum code to display further details for */
const selectedNodeId = window.Vue.ref<number | null>(null);

const isNodeDetailsModalOpen = window.Vue.computed({
    get: () => selectedNodeId.value !== null,
    set: (val: boolean) => {
        if (!val) selectedNodeId.value = null;
    },
});

/** The tag clicked by the user in order to filter to items having that tag. */
const selectedTag = window.Vue.ref<string>("");

/**
 * NOTE: There is an array in library/Schoolbox/Learning/Moment/Feed/Cursor.php whose values must match
 * the keys in this object.  If any change is made to these keys, a similar change should be made
 * to that array.
 */
const selectedFilters = window.Vue.reactive({
    goals: false,
    learningMoments: false,
    achievements: false,
    feedback: false,
});

const awardDetailModalEl = window.Vue.useTemplateRef<InstanceType<typeof AwardDetailModal> | null>(
    "awardDetailModalRef",
);

type FiltersState = {
    tag: string;
    filterNames: string[];
};

/**
 * Generate a filterState object from the URL parameters (if there are any).
 *
 * The URL is expected to have parameters like this:
 *    https://schoolbox.com.au/search/user/69?tag=Class%20Work&filterNames[]=feedback&filterNames[]=achievements
 *    - Both parameters ('tag' and 'filterNames') are optional
 *    - filterNames are expected to match the keys from this.selectedFilters.
 *
 * Example of return value:
 *    {
 *       filterNames: ['feedback', 'learningMoments'], { Array of keys from this.selectedFilters }
 *       tag: '' { String }
 *    }
 * @returns - -
 * @see getFiltersStateFromDom()
 */
function getFiltersStateFromUrl(): FiltersState {
    const url = new URL(window.location.href);

    const filtersState: FiltersState = { tag: "", filterNames: [] };

    for (let [key, value] of url.searchParams.entries()) {
        key = decodeURIComponent(key);
        value = decodeURIComponent(value);

        switch (key) {
            case "tag":
                filtersState.tag = value;
                break;

            case "filterNames[]":
                filtersState.filterNames.push(value);
                break;

            default:
            // Unrecognised key, so ignore.  Do nothing.
        }
    }

    return filtersState;
}

/**
 * Generate a filterState object from the current state of the DOM.
 * Since the selectedTag is not stored in the DOM, we get that from this.selectedTag.
 *
 * Example of return value:
 *    {
 *       filterNames: ['feedback', 'learningMoments'], { Array of keys from this.selectedFilters }
 *       tag: '' { String }
 *    }
 * @returns -
 * @see getFiltersStateFromUrl()
 */
function getFiltersStateFromDom(): FiltersState {
    const checkedFeedItemTypes: string[] = [];

    for (const filterName of keysOf(selectedFilters)) {
        if ($(`#filter-${filterName}`).prop("checked")) {
            checkedFeedItemTypes.push(filterName);
        }
    }

    return { filterNames: checkedFeedItemTypes, tag: selectedTag.value };
}

/**
 * @param filtersState -
 * Example of non-null filtersState object:
 *    {
 *       filterNames: ['feedback', 'learningMoments'],
 *       tag: '' { String }
 *    }
 */
function setSelectedTagAndFiltersToMatchState(filtersState: FiltersState | null): void {
    if (filtersState === null) {
        // Return the filters to their default state.
        selectedTag.value = "";

        for (const filterName of keysOf(selectedFilters)) {
            selectedFilters[filterName] = false;
        }
    } else {
        // Restore state of selectedTag.
        selectedTag.value = filtersState.tag;

        // Restore state of filter checkboxes.
        for (const filterName of keysOf(selectedFilters)) {
            selectedFilters[filterName] = filtersState.filterNames.includes(filterName);
        }
    }
}

type GroupedFeedItem = {
    date: Dayjs;
    items: FeedItem[];
};

/**
 * Groups feed items by the date which it was posted
 */
const groupedFeedItems = window.Vue.computed<GroupedFeedItem[]>(() => {
    const groupedFeedResultsByDate: Record<string, GroupedFeedItem> = {};
    data.value.forEach((v) => {
        const date = window.dayjs(v.date);
        const key = date.format("YYYYMMDD");
        if (!(key in groupedFeedResultsByDate)) {
            groupedFeedResultsByDate[key] = {
                date: window.dayjs(date).startOf("d"),
                items: [],
            };
        }

        groupedFeedResultsByDate[key].items.push(v);
    });

    // Flatten grouped feed items into an array
    const groupedItems = Object.values(groupedFeedResultsByDate).sort((a, b) => (a.date.isBefore(b.date) ? 1 : -1));

    // Ensure that each item within the group is sorted
    groupedItems.forEach((v) => {
        v.items.sort((a, b) => window.dayjs(b.date).diff(window.dayjs(a.date)));
    });

    return groupedItems;
});

/**
 * Appends feed items to the data list.
 * @param cursor -
 */
async function append(cursor: string | null): Promise<void> {
    const params = new URLSearchParams();
    if (cursor) {
        params.set("cursor", cursor);
    }

    params.set("filter", btoa(JSON.stringify(getFiltersStateFromDom())));

    try {
        if (!props.routes.getFeed) {
            throw new Error("Unable to access feed");
        }

        const response = await fetch(`${props.routes.getFeed}?${params.toString()}`);
        const responseData = (await response.json()) as {
            data: FeedItem[];
            metadata?: Metadata;
        };

        data.value = data.value.concat(responseData.data ? responseData.data : []);
        metadata.count = responseData.metadata?.count ? responseData.metadata.count : 0;
        metadata.cursor.current = responseData.metadata?.cursor?.current ? responseData.metadata.cursor.current : null;
        metadata.cursor.next = responseData.metadata?.cursor?.next ? responseData.metadata.cursor.next : null;
    } catch (error) {
        console.warn(error);
    } finally {
        showSkeletonLoader.value = false;
        showFeed.value = true;
    }
}

/**
 * Load more feed items for the student in question.
 * Once received, group them by date.
 */
async function load(): Promise<void> {
    showFeed.value = false;
    data.value = [];
    await append(null);
}

/**
 * Renders the created-at time as a string.
 * @param createdAt -
 * @returns - Created at date in human-readable format.
 */
function renderCreatedAt(createdAt: Dayjs): string {
    const now = window.dayjs().startOf("d");
    const dayDiff = createdAt.diff(now, "d");

    if (dayDiff === 0) {
        return "today";
    }
    if (dayDiff === -1) {
        return "yesterday";
    }
    if (dayDiff === 1) {
        return "tomorrow";
    }
    return window.dayjs.duration(dayDiff, "d").humanize(true);
}

/**
 * Does this feed item have a menu?
 * @param item -
 * @returns - whether this feed item has a menu
 */
function hasMenu(item: FeedItem["object"]): boolean {
    if ("modifyForm" in item._links && !!item._links.modifyForm) {
        return true;
    }

    if ("delete" in item._links && !!item._links.delete) {
        return true;
    }

    return false;
}

/**
 * Display the delete modal for a feed item
 * @param item -
 */
function showDeleteModal(item: FeedItem): void {
    currentlyDeletingItem.value = item;
    isDeleteModalOpen.value = true;
}

/**
 * Actually delete an item from the feed
 * @param targetItem -
 */
async function deleteFeedItem(targetItem: FeedItem): Promise<void> {
    const deleteRoute = "delete" in targetItem.object._links ? targetItem.object._links.delete : null;
    if (!deleteRoute) {
        throw new Error("Missing access to delete this evidence");
    }

    // awards, other things may also be deleted in the future, so ensure this is also the right data type
    const newData = data.value.filter((item) => {
        if (item.discriminator !== targetItem.discriminator) {
            return true;
        }
        return item.object.id !== targetItem.object.id;
    });

    const toast = window.sbProps.toastActivate("Deleting...");

    // Replacing the data in place in the same tick causes issues with
    // the MediaElement player and Foundation dropdowns for some reason.
    // Clear it first; then replace it with the filtered data in the
    // next tick.
    try {
        await fetch(deleteRoute, {
            method: "DELETE",
        });
        data.value = [];
        toast.timeout({ text: "Deleted", isSuccess: true });

        void window.Vue.nextTick(() => {
            data.value = newData;
            metadata.count -= 1;
        });
    } catch {
        toast.timeout({ text: "Could not delete", isSuccess: false });
    } finally {
        isDeleteModalOpen.value = false;
    }
}

/**
 * Open the view badge award modal.
 * @param badgeAward - The badge award object to display
 */
function openViewBadgeAwardModal(badgeAward: BadgeAward): void {
    if (!awardDetailModalEl.value) {
        throw new Error("Missing award detail modal element");
    }

    awardDetailModalEl.value.open(badgeAward);
}

/**
 * Format the date as specified
 * @param date -
 * @param format -
 * @returns the formatted date
 */
function formatDate(date: string | Dayjs, format?: string): string {
    return window.dayjs(date).format(format);
}

/**
 * Retrieve the class to use for the gradient background based on the grade
 * @param val - The grade gradient value, if it exists
 * @returns The gradient class to use
 */
function gradient(val: string | null): string {
    if (val === null) {
        return "gradient-none-bg"; // Gradients are disabled, or no grade exists
    }
    return `gradient-${val}-bg`;
}

/**
 * Load new content based on the changed state of the filters, and optionally simulate a redirect.
 * The point of simulating a redirect is to enable use of the back button to visit previous filter states, and
 * enable sharing of the URL of the page in a particular filter state.
 * @param $selectedTag -
 * @param simulateRedirect
 *  - Set this to true if you want a click of the browser 'back' button to return the user to the state before this function was called.
 */
function loadFilteredContent($selectedTag: string, simulateRedirect: boolean): void {
    // Note Regarding behaviour when all-checked/none-checked
    // ------------------------------------------------------
    // If all checkboxes are checked, we leave them that way.
    // We could have decided to uncheck all in this situation, to match the default state of all unchecked,
    // which behaves the same as if all were checked.  However, we decided it would be less confusing to the
    // user if checking the last checkbox resulted in all being checked rather than none being checked.

    selectedTag.value = $selectedTag;

    if (simulateRedirect) {
        // Push the old filters state to the history object.
        window.history.pushState(getFiltersStateFromUrl(), "", window.location.href);
    }

    // Get the new filters state from the DOM, and create a new URL to match that state.
    const filtersState = getFiltersStateFromDom();
    const newUrl = new URL(window.location.href);
    newUrl.searchParams.delete("tag");
    newUrl.searchParams.delete("filterNames[]");
    if (filtersState !== null) {
        if (filtersState.tag !== "") {
            newUrl.searchParams.append("tag", encodeURIComponent(filtersState.tag));
        }

        for (const filterName of filtersState.filterNames) {
            newUrl.searchParams.append("filterNames[]", encodeURIComponent(filterName));
        }
    }

    // Get new content based on changed filters state.
    void load();

    // Replace the current state in the history object with the new state (and set the current URL to match that state).
    window.history.replaceState(filtersState, "", newUrl.toString());
}

/**
 * Put the $moment into the Submitted state as-is
 * @param moment - The moment object to submit
 */
function submitMoment(moment: LearningMoment): void {
    const submitLink = moment._links?.submit;

    if (submitLink) {
        const toast = window.sbProps.toastActivate("Submitting...");

        fetch(submitLink, {
            method: "PUT",
        })
            .then(() => {
                toast.timeout({ text: "Submitted.", isSuccess: true });
                loadFilteredContent("", true);
            })
            .catch(() => toast.timeout({ text: "Submit failed", isSuccess: false }));
    }
}

/**
 * Put the $moment into the Published state as-is
 * @param moment - The moment object to publish
 */
function publishMoment(moment: LearningMoment): void {
    const publishLink = moment._links?.publish;

    if (publishLink) {
        const toast = window.sbProps.toastActivate("Publishing...");

        fetch(publishLink, {
            method: "PUT",
        })
            .then(() => {
                toast.timeout({ text: "Published.", isSuccess: true });
                loadFilteredContent("", true);
            })
            .catch(() => toast.timeout({ text: "Publish failed", isSuccess: false }));
    }
}

window.Vue.onMounted(async () => {
    document.addEventListener(userProfileConstants.events.AWARDS_UPDATED, () => void load());
    window.onpopstate = async (event): Promise<void> => {
        // NOTE: event.state should never be null because of the history.replaceState() call in this.loadFilteredContent().
        setSelectedTagAndFiltersToMatchState(event.state as FiltersState);
        await window.Vue.nextTick(); // Wait for Vue to sync checkboxes to match this.selectedFilters (due to 'v-model' attribute on relevant HTML input).
        loadFilteredContent(selectedTag.value, false);
    };

    setSelectedTagAndFiltersToMatchState(getFiltersStateFromUrl());
    await window.Vue.nextTick(); // Wait for Vue to sync checkboxes to match this.selectedFilters (due to 'v-model' attribute on relevant HTML input).
    loadFilteredContent(selectedTag.value, false);
});
</script>

<template>
    <div>
        <div class="feed filter-container">
            <div class="feed-column left"></div>
            <section class="activity-feed">
                <div class="profile-learning-actions">
                    <div>
                        <a
                            v-if="props.routes.getCreateEvidenceForm"
                            href="#"
                            title="Share Learning"
                            class="learning"
                            data-reveal-id="shareLearningMoment"
                            data-test="learning-moment-evidence-share"
                        >
                            <sbx-icon name="share" size="2rem"></sbx-icon>
                            <span>Share Learning</span>
                        </a>
                        <a
                            v-if="props.routes.getSetAsGoalForm"
                            :href="getReturnUrl(props.routes.getSetAsGoalForm)"
                            title="Set goals"
                            class="goals"
                        >
                            <sbx-icon name="goal" size="2rem"></sbx-icon>
                            <span>Set Goals</span>
                        </a>
                        <a
                            v-if="props.routes.getSetAsGoalForSelfForm"
                            :href="getReturnUrl(props.routes.getSetAsGoalForSelfForm)"
                            title="Set goals"
                            class="goals"
                        >
                            <sbx-icon name="goal" size="2rem"></sbx-icon>
                            <span>Set My Goals</span>
                        </a>
                        <a
                            v-if="props.routes.getAwardAsAchievementForm"
                            :href="getReturnUrl(props.routes.getAwardAsAchievementForm)"
                            title="Award achievement"
                            class="achievement"
                        >
                            <sbx-icon name="achievement" size="2rem"></sbx-icon>
                            <span>Award Achievement</span>
                        </a>
                    </div>
                </div>
            </section>
            <div class="feed-column right"></div>
        </div>

        <div class="feed filter-container">
            <div class="feed-column left"></div>
            <section class="activity-feed">
                <div class="VueTables">
                    <div class="filters">
                        <div class="checklist">
                            <label class="title">Filter to:</label>
                            <ul class="option-list l-flex-row l-gap-s">
                                <li v-if="showLearningMoments">
                                    <input
                                        id="filter-learningMoments"
                                        v-model="selectedFilters.learningMoments"
                                        type="checkbox"
                                        data-filter-checkbox
                                        name="includeLearningMoments"
                                        @click="loadFilteredContent('', true)"
                                    />
                                    <label for="filter-learningMoments">Learning Moments</label>
                                </li>
                                <li v-if="showGoals">
                                    <input
                                        id="filter-goals"
                                        v-model="selectedFilters.goals"
                                        type="checkbox"
                                        data-filter-checkbox
                                        name="includeGoals"
                                        :true-value="true"
                                        :false-value="false"
                                        @click="loadFilteredContent('', true)"
                                    />
                                    <label for="filter-goals">Goals</label>
                                </li>
                                <li v-if="showAchievements">
                                    <input
                                        id="filter-achievements"
                                        v-model="selectedFilters.achievements"
                                        type="checkbox"
                                        data-filter-checkbox
                                        name="includeAchievements"
                                        @click="loadFilteredContent('', true)"
                                    />
                                    <label for="filter-achievements">Achievements</label>
                                </li>
                                <li v-if="showFeedback">
                                    <input
                                        id="filter-feedback"
                                        v-model="selectedFilters.feedback"
                                        type="checkbox"
                                        data-filter-checkbox
                                        name="includeDueWork"
                                        @click="loadFilteredContent('', true)"
                                    />
                                    <label for="filter-feedback">Feedback</label>
                                </li>
                            </ul>
                        </div>
                        <div class="filters-description" data-filters-description>
                            <p v-if="selectedTag !== ''">
                                Showing tag "{{ selectedTag }}"
                                <span id="removeFilters" class="anchor-style" @click="loadFilteredContent('', true)">
                                    (remove)
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </section>
            <div class="feed-column right"></div>
        </div>

        <Transition name="fade" mode="out-in">
            <div v-if="showFeed">
                <div v-for="(itemGroup, groupIndex) in groupedFeedItems" :key="groupIndex" class="feed">
                    <div class="feed-column left"></div>
                    <section class="activity-feed">
                        <h2 class="subheader no-margin">
                            <time
                                :datetime="formatDate(itemGroup.date, 'YYYY-MM-DD')"
                                :title="formatDate(itemGroup.date, dateDisplayFormats.dayjsDateLong)"
                            >
                                {{ renderCreatedAt(itemGroup.date) }}
                            </time>
                        </h2>
                        <ul class="information-list">
                            <li
                                v-for="(item, itemIndex) in itemGroup.items"
                                :key="itemIndex"
                                class="feed-item"
                                :data-test="`activity-feed-item-${item.discriminator}-${item.object.id}`"
                            >
                                <template v-if="item.discriminator === FeedDiscriminator.LEARNING_MOMENT">
                                    <!-- This moment isn't necessarily editable -->
                                    <div
                                        :class="{
                                            'actions-small-1': hasMenu(item.object),
                                            'actions-small-0': !hasMenu(item.object),
                                        }"
                                    >
                                        <div class="card small-12 compact">
                                            <component
                                                :is="item.object.creator?._links.profile ? 'a' : 'span'"
                                                :href="item.object.creator?._links.profile ?? undefined"
                                                :title="
                                                    item.object.creator?._links.profile ? 'View Profile' : undefined
                                                "
                                                class="l-gap-s"
                                            >
                                                <div class="l-flex-row l-flex--align-center">
                                                    <template v-if="item.object.creator">
                                                        <img
                                                            :src="item.object.creator?._links.avatar"
                                                            :alt="
                                                                item.object.creator?._links.profile
                                                                    ? 'View Profile'
                                                                    : undefined
                                                            "
                                                        />
                                                        <p>{{ item.object.creator.fullName }}</p>
                                                    </template>

                                                    <time
                                                        v-if="item.object.createdAt"
                                                        class="pipe meta"
                                                        :datetime="formatDate(item.object.createdAt)"
                                                        :title="
                                                            formatDate(
                                                                item.object.createdAt,
                                                                dateDisplayFormats.dayjsDatetime,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            formatDate(
                                                                item.object.createdAt,
                                                                dateDisplayFormats.dayjsTime,
                                                            )
                                                        }}
                                                    </time>
                                                </div>

                                                <!-- Only show status that isn't published, since that is the default state -->
                                                <span
                                                    v-if="item.object.currentState !== EvidenceStatus.PUBLISHED"
                                                    class="label l-mar--0"
                                                    :class="`label--moment-${item.object.currentState}`"
                                                    :data-test="`learning-moment-status-${item.object.currentState}`"
                                                >
                                                    {{ evidenceStatusLabels[item.object.currentState] }}
                                                </span>
                                            </component>
                                        </div>
                                        <nav v-if="hasMenu(item.object)">
                                            <sbx-pop-out
                                                :content-id="`learning-moment-${item.object.id}-popout`"
                                                :data-test="`sbx-pop-out-learning-moment-${item.object.id}`"
                                            ></sbx-pop-out>
                                        </nav>
                                        <ul
                                            v-if="hasMenu(item.object)"
                                            :id="'learning-moment-' + String(item.object.id) + '-popout'"
                                            class="f-dropdown"
                                            data-dropdown-content
                                        >
                                            <li>
                                                <a
                                                    v-if="item.object._links.modifyForm"
                                                    :href="getReturnUrl(item.object._links.modifyForm)"
                                                    class="sbx-button--has-icon"
                                                    title="Modify"
                                                    :data-test="`learning-moment-popout-option-modify-${item.object.id}`"
                                                >
                                                    <sbx-icon name="modify"></sbx-icon>Modify
                                                </a>
                                            </li>
                                            <li>
                                                <a
                                                    v-if="item.object._links.delete"
                                                    class="sbx-button--has-icon"
                                                    title="Delete"
                                                    :data-test="`learning-moment-delete-${item.object.id}`"
                                                    @click.prevent="showDeleteModal(item)"
                                                >
                                                    <sbx-icon name="delete"></sbx-icon>
                                                    Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="feed-body small-12 columns">
                                        <moment-evidence-gallery :moment-evidence="item.object.evidence" />

                                        <!-- Display moment body, with media players initialised -->
                                        <media-article
                                            v-if="item.object.body"
                                            data-test="learning-moment-body"
                                            :content="item.object.body"
                                        ></media-article>

                                        <!-- Display curriculum codes and tags -->
                                        <div
                                            v-if="
                                                (item.object.curriculumNodes &&
                                                    item.object.curriculumNodes.length > 0) ||
                                                (item.object.tags && item.object.tags.length > 0)
                                            "
                                            class="tags"
                                        >
                                            <p class="meta">
                                                <codes-list
                                                    v-if="item.object.curriculumNodes !== null"
                                                    :nodes="item.object.curriculumNodes"
                                                    @selected="(node) => (selectedNodeId = node.id)"
                                                />
                                                <template
                                                    v-if="item.object.tags !== null && item.object.tags.length > 0"
                                                >
                                                    <br
                                                        v-if="
                                                            item.object.curriculumNodes !== null &&
                                                            item.object.curriculumNodes.length > 0
                                                        "
                                                    />
                                                    <span>Tags:</span>
                                                    <template
                                                        v-for="(tag, index) in item.object.tags"
                                                        :key="`${index}-${tag.name}`"
                                                    >
                                                        <template v-if="index !== 0">, </template>
                                                        <span
                                                            class="anchor-style"
                                                            title="Click to only show items with this tag."
                                                            @click="loadFilteredContent(tag.name, true)"
                                                            >{{ tag.name }}</span
                                                        >
                                                    </template>
                                                </template>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="feed-comments small-12 columns">
                                        <comment-thread
                                            :thread="item.object.discussionThread ?? undefined"
                                            :create-thread-endpoint="item.object._links.createThread ?? undefined"
                                            :can-comment="props.canComment"
                                        />
                                    </div>

                                    <!-- Learning Moment Moderation: Action Buttons -->
                                    <!-- These are based on role types -->
                                    <div class="learning-moment-cta small-12 columns">
                                        <ul class="flex-list buttons">
                                            <!-- Student buttons -->
                                            <template v-if="'student' === currentUser.role.type">
                                                <!-- current user must be the author of the learning moment to have these actions -->
                                                <template
                                                    v-if="
                                                        item.object.creator && currentUser.id === item.object.creator.id
                                                    "
                                                >
                                                    <template
                                                        v-if="
                                                            'draft' === item.object.currentState ||
                                                            'submitted' === item.object.currentState ||
                                                            'rejected' === item.object.currentState
                                                        "
                                                    >
                                                        <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                        <li v-if="item.object._links.modifyForm">
                                                            <a
                                                                class="button"
                                                                data-test="learning-moment-action-modify"
                                                                :href="getReturnUrl(item.object._links.modifyForm)"
                                                            >
                                                                Modify
                                                            </a>
                                                        </li>
                                                    </template>

                                                    <template v-if="'draft' === item.object.currentState">
                                                        <!-- Submit button available when a learning moment is: draft -->
                                                        <template v-if="moderationRequired">
                                                            <li>
                                                                <button
                                                                    class="submit"
                                                                    data-test="learning-moment-action-submit"
                                                                    @click="submitMoment(item.object)"
                                                                >
                                                                    Submit for approval
                                                                </button>
                                                            </li>
                                                        </template>
                                                        <template v-else>
                                                            <li>
                                                                <button
                                                                    class="submit"
                                                                    data-test="learning-moment-action-publish"
                                                                    @click="publishMoment(item.object)"
                                                                >
                                                                    Publish
                                                                </button>
                                                            </li>
                                                        </template>
                                                    </template>
                                                </template>
                                            </template>

                                            <!-- Parent buttons -->
                                            <template
                                                v-else-if="
                                                    'parent' === currentUser.role.type && props.momentParentCreate
                                                "
                                            >
                                                <template
                                                    v-if="
                                                        'draft' === item.object.currentState ||
                                                        'submitted' === item.object.currentState ||
                                                        'rejected' === item.object.currentState
                                                    "
                                                >
                                                    <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                    <li v-if="item.object._links.modifyForm">
                                                        <a
                                                            class="button"
                                                            data-test="learning-moment-action-modify"
                                                            :href="getReturnUrl(item.object._links.modifyForm)"
                                                        >
                                                            Modify
                                                        </a>
                                                    </li>
                                                </template>

                                                <template v-if="'draft' === item.object.currentState">
                                                    <!-- Publish button available when a learning moment is: draft/submitted/rejected -->
                                                    <li>
                                                        <button
                                                            class="submit"
                                                            data-test="learning-moment-action-publish"
                                                            @click="publishMoment(item.object)"
                                                        >
                                                            Publish
                                                        </button>
                                                    </li>
                                                </template>
                                            </template>

                                            <!-- Staff buttons -->
                                            <template v-else-if="'staff' === currentUser.role.type">
                                                <template
                                                    v-if="
                                                        'draft' === item.object.currentState ||
                                                        'submitted' === item.object.currentState ||
                                                        'rejected' === item.object.currentState
                                                    "
                                                >
                                                    <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                    <li v-if="item.object._links.modifyForm">
                                                        <a
                                                            class="button"
                                                            :href="getReturnUrl(item.object._links.modifyForm)"
                                                            data-test="learning-moment-action-modify"
                                                        >
                                                            Modify
                                                        </a>
                                                    </li>
                                                </template>

                                                <template
                                                    v-if="
                                                        'draft' === item.object.currentState ||
                                                        'submitted' === item.object.currentState ||
                                                        'rejected' === item.object.currentState
                                                    "
                                                >
                                                    <!-- Publish button available when a learning moment is: draft/submitted/rejected -->
                                                    <li v-if="item.object._links.publish">
                                                        <button
                                                            class="submit"
                                                            data-test="learning-moment-action-publish"
                                                            @click="publishMoment(item.object)"
                                                        >
                                                            {{ item.object._labels.publish }}
                                                        </button>
                                                    </li>
                                                </template>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <template v-if="item.discriminator === FeedDiscriminator.BADGE_AWARD">
                                    <div class="card badge-card small-12">
                                        <a href="#" @click.prevent="openViewBadgeAwardModal(item.object)">
                                            <badge-image
                                                :badge="item.object.badge"
                                                :css="item.object.status === 'goal' ? 'goal' : ''"
                                            ></badge-image>
                                            <h3>{{ item.object.badge.name }}</h3>
                                            <p v-if="item.object.awardedAt || item.object.awardedBy" class="meta">
                                                <template v-if="item.object.status === 'achievement'">
                                                    Achievement awarded
                                                </template>
                                                <template v-if="item.object.status === 'goal'">Goal set</template>
                                                <template v-if="item.object.awardedBy">
                                                    by {{ item.object.awardedBy.fullName }}
                                                </template>
                                                <template v-if="item.object.awardedBy && item.object.awardedAt">
                                                    |
                                                </template>
                                                <time
                                                    v-if="item.object.awardedAt"
                                                    class="meta"
                                                    :datetime="formatDate(item.object.awardedAt)"
                                                    :title="
                                                        formatDate(
                                                            item.object.awardedAt,
                                                            dateDisplayFormats.dayjsDatetime,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatDate(item.object.awardedAt, dateDisplayFormats.dayjsTime)
                                                    }}
                                                </time>
                                            </p>
                                        </a>
                                    </div>
                                </template>
                                <template
                                    v-if="
                                        item.discriminator === FeedDiscriminator.DUE_WORK_FEEDBACK ||
                                        item.discriminator === FeedDiscriminator.QUIZ_FEEDBACK
                                    "
                                >
                                    <div class="flex-grade">
                                        <div class="small-12 card columns">
                                            <div v-if="item.object.assessment">
                                                <span class="icon-feedback"></span>
                                                <component
                                                    :is="item.object._links.summary ? 'a' : 'span'"
                                                    :href="
                                                        item.object._links.summary
                                                            ? item.object._links.summary
                                                            : undefined
                                                    "
                                                >
                                                    {{ item.object.assessment.title }}
                                                </component>
                                            </div>
                                            <p class="meta">
                                                Reviewed by
                                                <a
                                                    v-if="item.object.instructor"
                                                    :href="item.object.instructor._links.profile"
                                                    title="View Profile"
                                                >
                                                    {{ item.object.instructor.fullName }}
                                                </a>
                                                <span v-else>Unknown</span>
                                                <template v-if="item.object.assessment">
                                                    in {{ item.object.assessment?.folderName }}
                                                </template>
                                                |
                                                <time
                                                    class="meta"
                                                    :datetime="formatDate(item.object.publishedAt)"
                                                    :title="
                                                        formatDate(
                                                            item.object.publishedAt,
                                                            dateDisplayFormats.dayjsDatetime,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatDate(
                                                            item.object.publishedAt,
                                                            dateDisplayFormats.dayjsTime,
                                                        )
                                                    }}
                                                </time>
                                            </p>
                                        </div>
                                        <div
                                            v-if="item.object.mark"
                                            :class="`grade ${gradient(item.object.gradientMark)}`"
                                        >
                                            <span>{{ item.object.mark }}</span>
                                        </div>
                                    </div>
                                    <div class="content">
                                        <blockquote v-if="item.object.comment">
                                            <media-article
                                                data-test="activity-feed-assessment-comment"
                                                :content="item.object.comment"
                                            ></media-article>
                                        </blockquote>
                                        <a v-if="item.object._links.summary" :href="item.object._links.summary">
                                            View assessment details
                                        </a>
                                    </div>
                                </template>
                            </li>
                        </ul>
                    </section>
                    <div class="feed-column right"></div>
                </div>
            </div>
        </Transition>
        <div v-if="showSkeletonLoader" class="skeleton-feed">
            <ul class="information-list">
                <li v-for="n in 3" :key="n" class="row">
                    <div class="small-12 card skeleton">
                        <div class="img"></div>
                        <h3></h3>
                        <p class="meta"></p>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else>
            <div v-if="metadata.count === 0" class="empty-state">
                <sbx-icon name="advanced-list" size="2.5rem"></sbx-icon>

                <template v-if="currentUser.id === props.student.id">
                    <!-- Current user -->
                    <p>You don't have any activity yet.</p>

                    <!-- Can share moments? -->
                    <p v-if="props.routes.getCreateEvidenceForm || props.routes.getSetAsGoalForSelfForm">
                        <template v-if="props.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma
                             -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment"
                                >Share your learning</a
                            ><template v-if="props.routes.getSetAsGoalForm && props.routes.getAwardAsAchievementForm"
                                >, </template
                            ><template v-else-if="props.routes.getSetAsGoalForSelfForm"> or </template>
                        </template>

                        <a
                            v-if="props.routes.getSetAsGoalForSelfForm"
                            :href="getReturnUrl(props.routes.getSetAsGoalForSelfForm)"
                            title="Set a goal"
                        >
                            <!-- Different capitalization for if this is start or middle of a sentence -->
                            <template v-if="props.routes.getCreateEvidenceForm">set a goal</template>
                            <template v-else>Set a goal</template>
                        </a>

                        to see it appear in your activity feed.
                    </p>
                </template>

                <template v-else>
                    <!-- Parent or student -->
                    <p>{{ props.student.givenName }} doesn't have any activity yet.</p>

                    <!-- What can the current user do? -->

                    <!-- Can share moments? -->
                    <p
                        v-if="
                            props.routes.getCreateEvidenceForm ||
                            props.routes.getSetAsGoalForm ||
                            props.routes.getAwardAsAchievementForm
                        "
                    >
                        <template v-if="props.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma
                             -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment"
                                >Share a learning moment</a
                            ><template v-if="props.routes.getSetAsGoalForm && props.routes.getAwardAsAchievementForm"
                                >, </template
                            ><template
                                v-else-if="props.routes.getSetAsGoalForm || props.routes.getAwardAsAchievementForm"
                            >
                                or
                            </template>
                        </template>

                        <a
                            v-if="props.routes.getSetAsGoalForm"
                            :href="getReturnUrl(props.routes.getSetAsGoalForm)"
                            title="Set a goal"
                        >
                            <!-- Different capitalization for if this is start or middle of a sentence -->
                            <template v-if="props.routes.getCreateEvidenceForm">set a goal</template>
                            <template v-else>Set a goal</template>
                        </a>
                        <template v-if="props.routes.getAwardAsAchievementForm"> or </template>

                        <a
                            v-if="props.routes.getAwardAsAchievementForm"
                            :href="getReturnUrl(props.routes.getAwardAsAchievementForm)"
                            title="Award an achievement"
                        >
                            <template v-if="props.routes.getCreateEvidenceForm || props.routes.getSetAsGoalForm"
                                >award an achievement</template
                            >
                            <template v-else>Award an achievement</template>
                        </a>

                        to see it appear here.
                    </p>

                    <!-- Can't share moments? -->
                    <p v-else>
                        When {{ props.student.givenName }} or their teacher shares learning, it will appear here.
                    </p>
                </template>
            </div>

            <div
                v-else-if="metadata.cursor && metadata.cursor.next && metadata.count > data.length && data.length > 0"
                class="feed-load-more content"
            >
                <button class="load-more expand" title="Load more" @click="append(metadata.cursor.next)">
                    Load more
                </button>
            </div>
        </div>

        <basic-modal v-model:is-open="isNodeDetailsModalOpen" :has-background-click="true">
            <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
        </basic-modal>

        <basic-modal v-model:is-open="isDeleteModalOpen" has-background-click>
            <h2>Please confirm</h2>
            <p>Are you sure you wish to delete this Learning Moment?</p>
            <ul class="flex-list buttons">
                <li v-if="currentlyDeletingItem !== null">
                    <a
                        id="confirm-delete-learning-moment"
                        class="button submit"
                        data-test="learning-moment-delete-confirm"
                        @click.prevent="deleteFeedItem(currentlyDeletingItem)"
                    >
                        Delete
                    </a>
                </li>
                <li><a class="button" @click.prevent="isDeleteModalOpen = false"> Cancel </a></li>
            </ul>
        </basic-modal>

        <award-detail-modal ref="awardDetailModalRef" />
    </div>
</template>
<style>
.anchor-style {
    cursor: pointer;
    text-decoration: underline;
}

.checklist {
    padding-left: 0.5rem;
}

.checklist > label,
.checklist > ul,
.option-list > li {
    display: flex;
}

.checklist > label,
.option-list > li {
    margin-right: 2rem;
}

@media (max-width: 500px) {
    .option-list > li:nth-child(odd) {
        width: 9rem;
    }
}

.filters-description {
    font-style: italic;
}

.fade-enter-active,
.fade-leave-active {
    transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.fade-enter-to,
.fade-leave-from {
    opacity: 1;
}

.learning-moment-cta ul {
    margin-bottom: 1rem;
}
</style>
