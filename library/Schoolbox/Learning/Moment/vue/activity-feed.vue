<script setup lang="ts">
import type { Dayjs } from "dayjs";
import { Template } from "@js/editor/types/editorTypes";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import foundationMixin from "@schoolboxVue/components/foundation-mixin";
import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import AwardDetailModal from "@schoolboxBadge/Award/vue/award-detail-modal.vue";
import BadgeImage from "@schoolboxBadge/vue/badge-image.vue";
import type { BadgeAward } from "@schoolboxBadge/templates/types/BadgeAward";
import type { DueWorkFeedback, QuizFeedback } from "@schoolboxLibrary/Learning/templates/types/activityTypes";
import userProfileConstants from "../../vue/user-profile/constants";
import CommentThread from "./comment-thread.vue";
import { EvidenceStatus, evidenceStatusLabels } from "./evidenceTypes";
import type { LearningMoment } from "./evidenceTypes";

// TODO:
// mixins: [foundationMixin, cdnPrefixMixin],

interface Props {
    siteVersion: string;
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
    evidenceParentCreate: boolean;
    /** Feedback editor templates to use in the comment field */
    editorTemplates?: Template[];
    /** Whether to use tokens in the comment editor */
    editorHasTokens?: boolean;
}

const props = defineProps<Props>();

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
const metadata = window.Vue.reactive({
    count: null,
    cursor: null,
});
const showSkeletonLoader = window.Vue.ref(true);
const currentlyDeletingEvidence = window.Vue.ref<FeedItem | null>(null);
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

const showFeed = window.Vue.ref(true);

// TODO check, also probably doesnt need to be computed
const config = window.Vue.computed(() => window.learningEvidenceFeedConfig);
const dateDisplayFormats = window.Vue.computed(() => window.dateDisplayFormats);

const awardDetailModalEl = window.Vue.useTemplateRef<InstanceType<typeof AwardDetailModal> | null>(
    "awardDetailModalRef",
);

type GroupedFeedResult = {
    date: Dayjs;
    evidence: FeedItem[];
};

/**
 * Groups learning evidence / badges by the date which it was posted.
 * @param feedResults -
 * @returns -
 */
function groupFeedResults(feedResults: FeedItem[]): GroupedFeedResult[] {
    const groupedEvidenceByDate: Record<string, GroupedFeedResult> = {};
    feedResults.forEach((v) => {
        const date = window.dayjs(v.date);
        const key = date.format("YYYYMMDD");
        if (!(key in groupedEvidenceByDate)) {
            groupedEvidenceByDate[key] = {
                date: window.dayjs(date).startOf("d"),
                evidence: [],
            };
        }

        v.date = date; // TODO: don't mutate the original object...
        groupedEvidenceByDate[key].evidence.push(v);
    });

    // Flatten grouped evidence into an array
    const groupedEvidence = Object.values(groupedEvidenceByDate).sort((a, b) => (a.date.isBefore(b.date) ? 1 : -1));

    // Ensure that each evidence within the group is sorted
    groupedEvidence.forEach((v) => {
        v.evidence.sort((a, b) => (a.date.isBefore(b.date) ? 1 : -1));
    });

    return groupedEvidence;
}

const groupedEvidence = window.Vue.computed(() => groupFeedResults(data.value));

const currentCount = window.Vue.computed(() => data.value.length);

/**
 * Appends learning evidence to the data list.
 * @param cursor -
 */
async function append(cursor: string | null): Promise<void> {
    const params = new URLSearchParams();
    if (cursor) {
        params.set("cursor", cursor);
    }

    params.set("filter", btoa(JSON.stringify(getFiltersStateFromDom())));

    try {
        const response = await fetch(`${config.value.routes.getFeed}?${params}`);
        const responseData = await response.json();
        data.value = data.value.concat(responseData.data ? responseData.data : []);
        metadata.count = responseData.metadata?.count ? responseData.metadata.count : 0;
        metadata.cursor = responseData.metadata?.cursor ? responseData.metadata.cursor : null;
    } catch (error) {
        console.warn(error);
    } finally {
        showSkeletonLoader.value = false;
        showFeed.value = true;
    }
}

/**
 * Load learning evidence for the student in question.
 *
 * Once received, group them by date.
 */
function load(): void {
    showFeed.value = false;
    data.value = [];
    append(null);
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
 * Open the share learning moment modal.
 */
function openShareLearningMomentModal(): void {
    window.jQuery("#shareLearningMoment").foundation("reveal", "open");
}

/**
 * Does this evidence have a menu?
 *
 * @param evidence -
 * @returns - whether this evidence has a menu
 */
function hasMenu(evidence: FeedItem["object"]): boolean {
    return Boolean(evidence?._links?.modifyForm || evidence?._links?.deleteForm);
}

/**
 * Display the delete modal for a piece of evidence.
 * @param evidence -
 */
function showDeleteModal(evidence: FeedItem): void {
    currentlyDeletingEvidence.value = evidence;
    isDeleteModalOpen.value = true;
}

/**
 * Actually delete this evidence.
 * @param evidence -
 */
async function deleteEvidence(evidence: FeedItem): Promise<void> {
    const toast = window.sbProps.toastActivate("Deleting...");

    //awards, other things may also be deleted in the future, so ensure this is also the right data type
    const newData = this.data.filter((data) => {
        if (data.discriminator !== evidence.discriminator) {
            return true;
        }
        return data.object.id !== evidence.object.id;
    });

    // Replacing the data in place in the same tick causes issues with
    // the MediaElement player and Foundation dropdowns for some reason.
    // Clear it first; then replace it with the filtered data in the
    // next tick.
    try {
        await fetch(evidence.object._links.delete, {
            method: "DELETE",
        });
        data.value = [];
        toast.timeout({ text: "Deleted", isSuccess: true });
        window.Vue.nextTick(() => {
            data.value = newData;
            metadata.count -= 1;
        });
    } catch (error) {
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
function formatDate(date: string | Dayjs, format: string): string {
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
    let filtersState = getFiltersStateFromDom();
    let newUrl = new URL(window.location.href);
    newUrl.searchParams.delete("tag");
    newUrl.searchParams.delete("filterNames[]");
    if (filtersState !== null) {
        if (filtersState.tag !== "") {
            newUrl.searchParams.append("tag", encodeURIComponent(filtersState.tag));
        }

        for (let filterName of filtersState.filterNames) {
            newUrl.searchParams.append("filterNames[]", encodeURIComponent(filterName));
        }
    }

    // Get new content based on changed filters state.
    load();

    // Replace the current state in the history object with the new state (and set the current URL to match that state).
    window.history.replaceState(filtersState, "", newUrl.toString());
}

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

    for (const filterName of Object.keys(selectedFilters)) {
        if ($("#filter-" + filterName).prop("checked")) {
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

        for (let filterName of Object.keys(selectedFilters)) {
            selectedFilters[filterName] = false;
        }
    } else {
        // Restore state of selectedTag.
        selectedTag.value = filtersState.tag;

        // Restore state of filter checkboxes.
        for (const filterName of Object.keys(selectedFilters)) {
            selectedFilters[filterName] = filtersState.filterNames.includes(filterName);
        }
    }
}

/**
 * Put the $evidence into the Submitted state as-is
 * @param evidence - The evidence object to submit
 */
function submitEvidence(evidence: LearningMoment): void {
    const submitLink = evidence?._links?.submit;

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
 * Put the $evidence into the Published state as-is
 * @param evidence - The evidence object to publish
 */
function publishEvidence(evidence: LearningMoment): void {
    const publishLink = evidence?._links?.publish;

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
    document.addEventListener(userProfileConstants.events.AWARDS_UPDATED, () => load());
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
                        <template v-if="config.routes.getCreateEvidenceForm">
                            <a
                                href="#"
                                title="Share Learning"
                                class="learning icon-share"
                                data-reveal-id="shareLearningMoment"
                                data-test="learning-moment-evidence-share"
                                ><span>Share Learning</span></a
                            >
                        </template>
                        <template v-if="config.routes.getSetAsGoalForm">
                            <a
                                :href="
                                    config.routes.getSetAsGoalForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Set goals"
                                class="goals icon-goal"
                                ><span>Set Goals</span></a
                            >
                        </template>
                        <template v-if="config.routes.getSetAsGoalForSelfForm">
                            <a
                                :href="
                                    config.routes.getSetAsGoalForSelfForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Set goals"
                                class="goals icon-goal"
                                ><span>Set My Goals</span></a
                            >
                        </template>
                        <template v-if="config.routes.getAwardAsAchievementForm">
                            <a
                                :href="
                                    config.routes.getAwardAsAchievementForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Award achievement"
                                class="achievement icon-achievement"
                                ><span>Award Achievement</span></a
                            >
                        </template>
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
                            <ul class="option-list">
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
                            <template v-if="selectedTag !== ''">
                                <p>
                                    Showing tag "{{ selectedTag }}"
                                    <span class="anchor-style" id="removeFilters" @click="loadFilteredContent('', true)"
                                        >(remove)</span
                                    >
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
            <div class="feed-column right"></div>
        </div>

        <Transition name="fade" mode="out-in">
            <div v-if="showFeed">
                <div class="feed" v-for="(evidenceGroup, index) in groupedEvidence" :key="index">
                    <div class="feed-column left"></div>
                    <section class="activity-feed">
                        <h2 class="subheader no-margin">
                            <time
                                :datetime="formatDate(evidenceGroup.date, 'YYYY-MM-DD')"
                                :title="formatDate(evidenceGroup.date, dateDisplayFormats.dayjsDateLong)"
                                >{{ renderCreatedAt(evidenceGroup.date) }}</time
                            >
                        </h2>
                        <ul class="information-list">
                            <li v-for="(evidence, index) in evidenceGroup.evidence" class="feed-item" :key="index">
                                <template v-if="evidence.discriminator === 'moment'">
                                    <!-- This evidence isn't necessarily editable -->
                                    <div
                                        :class="{
                                            'actions-small-1': hasMenu(evidence.object),
                                            'actions-small-0': !hasMenu(evidence.object),
                                        }"
                                    >
                                        <div class="card small-12 compact">
                                            <component
                                                :is="!evidence.object.creator.isDeleted ? 'a' : 'span'"
                                                :href="
                                                    !evidence.object.creator.isDeleted
                                                        ? evidence.object.creator._links.profile
                                                        : null
                                                "
                                                :title="!evidence.object.creator.isDeleted ? 'View Profile' : null"
                                                class="l-gap-s"
                                            >
                                                <div class="l-flex-row l-flex--align-center">
                                                    <img
                                                        :src="evidence.object.creator._links.avatar"
                                                        :alt="
                                                            !evidence.object.creator.isDeleted ? 'View Profile' : null
                                                        "
                                                    />
                                                    <p>{{ evidence.object.creator.fullName }}</p>

                                                    <time
                                                        class="pipe meta"
                                                        :datetime="formatDate(evidence.object.createdAt)"
                                                        :title="
                                                            formatDate(
                                                                evidence.object.createdAt,
                                                                dateDisplayFormats.dayjsDatetime,
                                                            )
                                                        "
                                                        >{{
                                                            formatDate(
                                                                evidence.object.createdAt,
                                                                dateDisplayFormats.dayjsTime,
                                                            )
                                                        }}</time
                                                    >
                                                </div>

                                                <!-- Only show status that isn't published, since that is the default state -->
                                                <span
                                                    v-if="evidence.object.currentState !== EvidenceStatus.PUBLISHED"
                                                    class="label l-mar--0"
                                                    :class="`label--moment-${evidence.object.currentState}`"
                                                >
                                                    {{ evidenceStatusLabels[evidence.object.currentState] }}
                                                </span>
                                            </component>
                                        </div>
                                        <nav v-if="hasMenu(evidence.object)">
                                            <sbx-pop-out
                                                :content-id="`evidence-${evidence.object.id}-dropdown`"
                                                :data-test="`sbx-pop-out-evidence-${evidence.object.id}`"
                                            ></sbx-pop-out>
                                        </nav>
                                        <ul
                                            v-if="hasMenu(evidence.object)"
                                            :id="'evidence-' + String(evidence.object.id) + '-dropdown'"
                                            class="f-dropdown"
                                            data-dropdown-content
                                        >
                                            <li>
                                                <a
                                                    v-if="evidence.object._links.modifyForm"
                                                    :href="
                                                        evidence.object._links.modifyForm + '?returnUrl=' + returnUrl
                                                    "
                                                    class="icon-modify"
                                                    title="Modify"
                                                    :data-test="`learning-moment-evidence-popout-option-modify-${evidence.object.id}`"
                                                    >Modify</a
                                                >
                                            </li>
                                            <li>
                                                <a
                                                    v-if="evidence.object._links.delete"
                                                    class="icon-delete"
                                                    title="Delete"
                                                    :data-test="`learning-moment-evidence-delete-${evidence.object.id}`"
                                                    @click.prevent="showDeleteModal(evidence)"
                                                    >Delete</a
                                                >
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="feed-body small-12 columns">
                                        <!-- Is there an attachment? Display it appropriately -->
                                        <article
                                            v-for="evidence in evidence.object.evidence"
                                            :key="evidence.storage.hash"
                                        >
                                            <storage-object
                                                :storage="evidence.storage"
                                                :filename="evidence.filename"
                                                data-test="learning-moment-evidence-media"
                                            />
                                        </article>

                                        <!-- Display evidence body, with media players initialised -->
                                        <media-article
                                            v-if="evidence.object.body"
                                            data-test="learning-moment-evidence-body"
                                            :content="evidence.object.body"
                                        ></media-article>

                                        <!-- Display curriculum codes and tags -->
                                        <div class="tags">
                                            <p
                                                class="meta"
                                                v-if="
                                                    evidence.object.curriculumNodes.length > 0 ||
                                                    evidence.object.tags.length > 0
                                                "
                                            >
                                                <codes-list
                                                    :nodes="evidence.object.curriculumNodes"
                                                    @selected="onCodeSelected"
                                                />
                                                <br
                                                    v-if="
                                                        evidence.object.tags.length > 0 &&
                                                        evidence.object.curriculumNodes.length > 0
                                                    "
                                                />
                                                <span v-if="evidence.object.tags.length > 0">Tags:</span>
                                                <template
                                                    v-for="(tag, index) in evidence.object.tags"
                                                    :key="`${index}-${tag.name}`"
                                                >
                                                    <template v-if="index !== 0">, </template>
                                                    <span
                                                        class="anchor-style"
                                                        @click="loadFilteredContent(tag.name, true)"
                                                        title="Click to only show items with this tag."
                                                        >{{ tag.name }}</span
                                                    >
                                                </template>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="feed-comments small-12 columns">
                                        <comment-thread
                                            :thread="evidence.object.discussionThread"
                                            :create-thread-endpoint="evidence.object._links.createThread"
                                            :can-comment="config.canComment"
                                        />
                                    </div>

                                    <!-- Learning Moment Moderation: Action Buttons -->
                                    <!-- These are based on role types -->
                                    <div class="learning-moment-cta small-12 columns">
                                        <ul class="flex-list buttons">
                                            <!-- Student buttons -->
                                            <template v-if="'student' === config.currentUser.role.type">
                                                <!-- current user must be the author of the learning moment to have these actions -->
                                                <template v-if="config.currentUser.id === evidence.object.creator.id">
                                                    <template
                                                        v-if="
                                                            'draft' === evidence.object.currentState ||
                                                            'submitted' === evidence.object.currentState ||
                                                            'rejected' === evidence.object.currentState
                                                        "
                                                    >
                                                        <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                        <li>
                                                            <a
                                                                class="button"
                                                                :href="
                                                                    evidence.object._links.modifyForm +
                                                                    '?returnUrl=' +
                                                                    returnUrl
                                                                "
                                                                >Modify</a
                                                            >
                                                        </li>
                                                    </template>

                                                    <template v-if="'draft' === evidence.object.currentState">
                                                        <!-- Submit button available when a learning moment is: draft -->
                                                        <template v-if="moderationRequired">
                                                            <li>
                                                                <button
                                                                    class="submit"
                                                                    @click="submitEvidence(evidence.object)"
                                                                >
                                                                    Submit for approval
                                                                </button>
                                                            </li>
                                                        </template>
                                                        <template v-else>
                                                            <li>
                                                                <button
                                                                    class="submit"
                                                                    @click="publishEvidence(evidence.object)"
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
                                                    'parent' === config.currentUser.role.type && evidenceParentCreate
                                                "
                                            >
                                                <template
                                                    v-if="
                                                        'draft' === evidence.object.currentState ||
                                                        'submitted' === evidence.object.currentState ||
                                                        'rejected' === evidence.object.currentState
                                                    "
                                                >
                                                    <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                    <li>
                                                        <a
                                                            class="button"
                                                            :href="
                                                                evidence.object._links.modifyForm +
                                                                '?returnUrl=' +
                                                                returnUrl
                                                            "
                                                            >Modify</a
                                                        >
                                                    </li>
                                                </template>

                                                <template v-if="'draft' === evidence.object.currentState">
                                                    <!-- Publish button available when a learning moment is: draft/submitted/rejected -->
                                                    <li>
                                                        <button
                                                            class="submit"
                                                            @click="publishEvidence(evidence.object)"
                                                        >
                                                            Publish
                                                        </button>
                                                    </li>
                                                </template>
                                            </template>

                                            <!-- Staff buttons -->
                                            <template v-else-if="'staff' === config.currentUser.role.type">
                                                <template
                                                    v-if="
                                                        'draft' === evidence.object.currentState ||
                                                        'submitted' === evidence.object.currentState ||
                                                        'rejected' === evidence.object.currentState
                                                    "
                                                >
                                                    <!-- Modify button available when a learning moment is: draft/submitted/rejected -->
                                                    <li>
                                                        <a
                                                            class="button"
                                                            :href="
                                                                evidence.object._links.modifyForm +
                                                                '?returnUrl=' +
                                                                returnUrl
                                                            "
                                                            >Modify</a
                                                        >
                                                    </li>
                                                </template>

                                                <template
                                                    v-if="
                                                        'draft' === evidence.object.currentState ||
                                                        'submitted' === evidence.object.currentState ||
                                                        'rejected' === evidence.object.currentState
                                                    "
                                                >
                                                    <!-- Publish button available when a learning moment is: draft/submitted/rejected -->
                                                    <li>
                                                        <button
                                                            class="submit"
                                                            @click="publishEvidence(evidence.object)"
                                                        >
                                                            {{ evidence.object._labels.publish }}
                                                        </button>
                                                    </li>
                                                </template>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <template v-if="evidence.discriminator === 'badgeAward'">
                                    <div class="card badge-card small-12">
                                        <a href="#" @click.prevent="openViewBadgeAwardModal(evidence.object)">
                                            <badge-image
                                                :badge="evidence.object.badge"
                                                :css="evidence.object.status === 'goal' ? 'goal' : ''"
                                            ></badge-image>
                                            <h3>{{ evidence.object.badge.name }}</h3>
                                            <p class="meta">
                                                <template v-if="evidence.object.status === 'achievement'"
                                                    >Achievement awarded</template
                                                >
                                                <template v-if="evidence.object.status === 'goal'">Goal set</template>
                                                by {{ evidence.object.awardedBy.fullName }}
                                                |
                                                <time
                                                    class="meta"
                                                    :datetime="formatDate(evidence.object.awardedAt)"
                                                    :title="
                                                        formatDate(
                                                            evidence.object.awardedAt,
                                                            dateDisplayFormats.dayjsDatetime,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatDate(
                                                            evidence.object.awardedAt,
                                                            dateDisplayFormats.dayjsTime,
                                                        )
                                                    }}
                                                </time>
                                            </p>
                                        </a>
                                    </div>
                                </template>
                                <template
                                    v-if="
                                        evidence.discriminator === 'dueWorkFeedback' ||
                                        evidence.discriminator === 'quizFeedback'
                                    "
                                >
                                    <div class="flex-grade">
                                        <div class="small-12 card columns">
                                            <div>
                                                <span class="icon-feedback"></span
                                                ><a :href="evidence.object._links.summary">{{
                                                    evidence.object.assessment.title
                                                }}</a>
                                            </div>
                                            <p class="meta">
                                                Reviewed by
                                                <a
                                                    v-if="evidence.object.instructor"
                                                    :href="evidence.object.instructor._links.profile"
                                                    title="View Profile"
                                                    >{{ evidence.object.instructor.fullName }}</a
                                                >
                                                <span v-else>Unknown</span>
                                                in {{ evidence.object.assessment.folderName }} |
                                                <time
                                                    class="meta"
                                                    :datetime="formatDate(evidence.object.publishedAt)"
                                                    :title="
                                                        formatDate(
                                                            evidence.object.publishedAt,
                                                            dateDisplayFormats.dayjsDatetime,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatDate(
                                                            evidence.object.publishedAt,
                                                            dateDisplayFormats.dayjsTime,
                                                        )
                                                    }}
                                                </time>
                                            </p>
                                        </div>
                                        <div
                                            v-if="evidence.object.mark"
                                            :class="`grade ${gradient(evidence.object.gradientMark)}`"
                                        >
                                            <span>{{ evidence.object.mark }}</span>
                                        </div>
                                    </div>
                                    <div class="content">
                                        <blockquote v-if="evidence.object.comment">
                                            <media-article
                                                data-test="activity-feed-assessment-comment"
                                                :content="evidence.object.comment"
                                            ></media-article>
                                        </blockquote>
                                        <a :href="evidence.object._links.summary"> View assessment details </a>
                                    </div>
                                </template>
                            </li>
                        </ul>
                    </section>
                    <div class="feed-column right"></div>
                </div>
            </div>
        </Transition>
        <div class="skeleton-feed" v-if="showSkeletonLoader">
            <ul class="information-list">
                <li class="row" v-for="n in 3" :key="n">
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
                <i class="icon-advanced-list"></i>

                <template v-if="config.currentUser.id === config.student.id">
                    <!-- Current user -->
                    <p>You don't have any activity yet.</p>

                    <!-- Can share moments? -->
                    <p v-if="config.routes.getCreateEvidenceForm || config.routes.getSetAsGoalForSelfForm">
                        <template v-if="config.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma
                             -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment"
                                >Share your learning</a
                            ><template v-if="config.routes.getSetAsGoalForm && config.routes.getAwardAsAchievementForm"
                                >, </template
                            ><template v-else-if="config.routes.getSetAsGoalForSelfForm"> or </template>
                        </template>

                        <template v-if="config.routes.getSetAsGoalForSelfForm">
                            <a
                                :href="
                                    config.routes.getSetAsGoalForSelfForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Set a goal"
                            >
                                <!-- Different capitalization for if this is start or middle of a sentence -->
                                <template v-if="config.routes.getCreateEvidenceForm">set a goal</template>
                                <template v-else>Set a goal</template>
                            </a>
                        </template>

                        to see it appear in your activity feed.
                    </p>
                </template>

                <template v-else>
                    <!-- Parent or student -->
                    <p>{{ config.student.givenName }} doesn't have any activity yet.</p>

                    <!-- What can the current user do? -->

                    <!-- Can share moments? -->
                    <p
                        v-if="
                            config.routes.getCreateEvidenceForm ||
                            config.routes.getSetAsGoalForm ||
                            config.routes.getAwardAsAchievementForm
                        "
                    >
                        <template v-if="config.routes.getCreateEvidenceForm">
                            <!--
                                All these tags need to be on the same line,
                                because otherwise there will be a space between
                                the link and the comma
                             -->
                            <a href="#" title="Share a learning moment" data-reveal-id="shareLearningMoment"
                                >Share a learning moment</a
                            ><template v-if="config.routes.getSetAsGoalForm && config.routes.getAwardAsAchievementForm"
                                >, </template
                            ><template
                                v-else-if="config.routes.getSetAsGoalForm || config.routes.getAwardAsAchievementForm"
                            >
                                or
                            </template>
                        </template>

                        <template v-if="config.routes.getSetAsGoalForm">
                            <a
                                :href="
                                    config.routes.getSetAsGoalForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Set a goal"
                            >
                                <!-- Different capitalization for if this is start or middle of a sentence -->
                                <template v-if="config.routes.getCreateEvidenceForm">set a goal</template>
                                <template v-else>Set a goal</template> </a
                            ><template v-if="config.routes.getAwardAsAchievementForm"> or </template>
                        </template>

                        <template v-if="config.routes.getAwardAsAchievementForm">
                            <a
                                :href="
                                    config.routes.getAwardAsAchievementForm +
                                    '?' +
                                    config.returnUrl.name +
                                    '=' +
                                    config.returnUrl.current
                                "
                                title="Award an achievement"
                            >
                                <template v-if="config.routes.getCreateEvidenceForm || config.routes.getSetAsGoalForm"
                                    >award an achievement</template
                                >
                                <template v-else>Award an achievement</template>
                            </a>
                        </template>

                        to see it appear here.
                    </p>

                    <!-- Can't share moments? -->
                    <p v-else>
                        When {{ config.student.givenName }} or their teacher shares learning, it will appear here.
                    </p>
                </template>
            </div>

            <div
                class="feed-load-more content"
                v-else-if="metadata.cursor.next && metadata.count > data.length && data.length > 0"
            >
                <button class="load-more expand" @click="append(metadata.cursor.next)" title="Load more">
                    Load more
                </button>
            </div>
        </div>

        <basic-modal v-model:is-open="isNodeDetailsModalOpen" :has-background-click="true">
            <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
        </basic-modal>

        <!-- TODO create a Vue equivalent of $.confirmDialog -->
        <basic-modal v-model:is-open="isDeleteModalOpen" has-background-click>
            <h2>Please confirm</h2>
            <p>Are you sure you wish to delete this Learning Moment?</p>
            <ul class="flex-list buttons">
                <li>
                    <a
                        id="confirm-delete-learning-moment"
                        class="button submit"
                        data-test="learning-moment-evidence-delete-confirm"
                        @click.prevent="deleteEvidence(currentlyDeletingEvidence)"
                        >Delete</a
                    >
                </li>
                <li><a @click.prevent="isDeleteModalOpen = false" class="button">Cancel</a></li>
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
    display: inline-block;
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
