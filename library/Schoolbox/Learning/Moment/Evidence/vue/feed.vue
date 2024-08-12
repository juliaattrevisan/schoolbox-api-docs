<script>
import MediaArticle from "@schoolboxVue/components/media-article";
import Storage from "@schoolboxVue/components/storage";
import CommentThread from "./comment-thread";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import Toast from "@schoolboxVue/components/toast";
import AwardDetailModal from "../../../Badge/Award/vue/award-detail-modal";
import BadgeImage from "../../../Badge/vue/badge-image";
import CodesList from "@schoolboxCurriculum/vue/codes-list";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import foundationMixin from "@schoolboxVue/components/foundation-mixin";
import userProfileConstants from "../../../vue/user-profile/constants";
import cdnPrefixMixin from "@schoolboxVue/components/cdn-prefix-mixin";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";

export default {
    components: {
        AwardDetailModal,
        Toast,
        RevealModal,
        MediaArticle,
        Storage,
        CommentThread,
        BadgeImage,
        CodesList,
        NodeDetails,
    },
    mixins: [foundationMixin, cdnPrefixMixin],
    props: {
        siteVersion: {
            type: String,
            required: true,
        },
        configGradeGradientEnabled: {
            type: Boolean,
            required: true,
        },
        showLearningMoments: {
            // whether to show learning moments within the feed
            type: Boolean,
            required: true,
        },
        showGoals: {
            // whether to show goals within the feed
            type: Boolean,
            required: true,
        },
        showAchievements: {
            // whether to show achievements within the feed
            type: Boolean,
            required: true,
        },
        showFeedback: {
            // whether to show feedback within the feed
            type: Boolean,
            required: true,
        },
    },
    data() {
        return {
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            currentlyDeletingEvidence: null,
            selectedNode: getEmptyNode(), // The selected curriculum code to display further details for.
            selectedTag: "", // The tag clicked by the user in order to filter to items having that tag.
            selectedFilters: {
                // NOTE: There is an array in library/Schoolbox/Learning/Moment/Feed/Cursor.php whose values must match
                //       the keys in this object.  If any change is made to these keys, a similar change should be made
                //       to that array.
                goals: false,
                learningMoments: false,
                achievements: false,
                feedback: false,
            },

            showFeed: true,
        };
    },
    computed: {
        jQuery: () => window.jQuery,
        config: () => window.learningEvidenceFeedConfig,
        dateDisplayFormats: () => window.dateDisplayFormats,
        groupedEvidence() {
            return this.groupFeedResults(this.data);
        },
        currentCount() {
            return data.length;
        },
        returnUrl() {
            return btoa(window.location.pathname);
        },
    },
    async mounted() {
        document.addEventListener(userProfileConstants.events.AWARDS_UPDATED, () => this.load());
        window.onpopstate = async (event) => {
            // NOTE: event.state should never be null because of the history.replaceState() call in this.loadFilteredContent().
            this.setSelectedTagAndFiltersToMatchState(event.state);
            await this.$nextTick(); // Wait for Vue to sync checkboxes to match this.selectedFilters (due to 'v-model' attribute on relevant HTML input).
            this.loadFilteredContent(this.selectedTag, false);
        };

        this.setSelectedTagAndFiltersToMatchState(this.getFiltersStateFromUrl());
        await this.$nextTick(); // Wait for Vue to sync checkboxes to match this.selectedFilters (due to 'v-model' attribute on relevant HTML input).
        this.loadFilteredContent(this.selectedTag, false);
    },

    methods: {
        /**
         * Load learning evidence for the student in question.
         *
         * Once received, group them by date.
         */
        load() {
            this.showFeed = false;
            this.data = [];
            this.append(null);
        },

        /**
         * Appends learning evidence to the data list.
         *
         * @param {string} cursor -
         */
        async append(cursor) {
            const params = {};
            if (cursor) {
                params.cursor = cursor;
            }

            params.filter = btoa(JSON.stringify(this.getFiltersStateFromDom()));

            try {
                const response = await fetch(this.config.routes.getFeed + "?" + new URLSearchParams(params));
                const responseData = await response.json();
                this.data = this.data.concat(responseData.data ? responseData.data : []);
                this.metadata.count = responseData.metadata?.count ? responseData.metadata.count : 0;
                this.metadata.cursor = responseData.metadata?.cursor ? responseData.metadata.cursor : null;
            } catch (error) {
                console.warn(error);
                this.loading = false;
            } finally {
                this.showSkeletonLoader = false;
                this.showFeed = true;
            }
        },

        /**
         * Groups learning evidence / badges by the date which it was posted.
         *
         * @param { Array } feedResults -
         * @returns { Array } -
         */
        groupFeedResults(feedResults) {
            let groupedEvidenceByDate = {};
            feedResults.forEach((v) => {
                let date = dayjs(v.date),
                    key = date.format("YYYYMMDD");
                if (!(key in groupedEvidenceByDate)) {
                    groupedEvidenceByDate[key] = {
                        date: dayjs(date).startOf("d"),
                        evidence: [],
                    };
                }
                v.date = date;
                groupedEvidenceByDate[key].evidence.push(v);
            });

            // Flatten grouped evidence into an array
            let groupedEvidence = Object.values(groupedEvidenceByDate).sort((a, b) =>
                a.date.isBefore(b.date) ? 1 : -1,
            );

            // Ensure that each evidence within the group is sorted
            groupedEvidence.forEach((v) => {
                v.evidence.sort((a, b) => (a.date.isBefore(b.date) ? 1 : -1));
            });

            return groupedEvidence;
        },

        /**
         * Renders the created-at time as a string.
         *
         * @param {object} createdAt -
         * @returns {string} - Created at date in human-readable format.
         */
        renderCreatedAt(createdAt) {
            let now = dayjs().startOf("d"),
                dayDiff = createdAt.diff(now, "d");
            if (dayDiff === 0) {
                return "today";
            }
            if (dayDiff === -1) {
                return "yesterday";
            }
            if (dayDiff === 1) {
                return "tomorrow";
            }
            return dayjs.duration(dayDiff, "d").humanize(true);
        },

        /**
         * Open the share learning moment modal.
         */
        openShareLearningMomentModal() {
            // shareLearningMoment
            this.jQuery("#shareLearningMoment").foundation("reveal", "open");
        },

        /**
         * Does this evidence have a menu?
         *
         * @param { object } evidence -
         * @returns { boolean } -
         */
        hasMenu(evidence) {
            return Boolean(evidence?._links?.modifyForm || evidence?._links?.deleteForm);
        },

        /**
         * Display the delete modal for a piece of evidence.
         *
         * @param { object } evidence -
         */
        showDeleteModal(evidence) {
            this.currentlyDeletingEvidence = evidence;
            this.$refs.deleteModal.open();
        },

        /**
         * Actually delete this evidence.
         *
         * @param { object } evidence -
         */
        async deleteEvidence(evidence) {
            this.$refs.toast.activate("Deleting...");

            //awards, other things may also be deleted in the future, so ensure this is also the right data type
            const removeEvidenceToDelete = (data) => {
                if (data.discriminator !== evidence.discriminator) {
                    return true;
                }
                return data.object.id !== evidence.object.id;
            };
            const newData = this.data.filter(removeEvidenceToDelete);

            // Replacing the data in place in the same tick causes issues with
            // the MediaElement player and Foundation dropdowns for some reason.
            // Clear it first; then replace it with the filtered data in the
            // next tick.
            try {
                await fetch(evidence.object._links.delete, {
                    method: "DELETE",
                });
                this.data = [];
                this.$refs.toast.timeout("Deleted", true);
                this.$nextTick(() => {
                    this.data = newData;
                    this.metadata.count -= 1;
                });
            } catch (error) {
                this.$refs.toast.timeout("Could not delete", false);
            } finally {
                this.$refs.deleteModal.close();
            }
        },

        /**
         * Open the view badge award modal.
         *
         * @param {object} badgeAward - The badge award object to display
         */
        openViewBadgeAwardModal(badgeAward) {
            this.$refs.awardDetailModal.open(badgeAward);
        },

        /**
         * Format the date as specified
         *
         * @param {Date} date -
         * @param {string} format -
         * @returns {string} -
         */
        formatDate(date, format) {
            return dayjs(date).format(format);
        },

        gradient(data) {
            let gradientLevel = this.configGradeGradientEnabled ? (parseInt(data) / 10).toFixed() : "none";
            return `gradient-${gradientLevel}-bg`;
        },

        /**
         * to open up the node details modal
         *
         * @param {object} node -
         */
        onCodeSelected(node) {
            this.selectedNode = node;
            this.$refs.nodeDetailsModal.open();
        },

        /**
         * Load new content based on the changed state of the filters, and optionally simulate a redirect.
         * The point of simulating a redirect is to enable use of the back button to visit previous filter states, and
         * enable sharing of the URL of the page in a particular filter state.
         *
         * @param { string } selectedTag -
         * @param { boolean } simulateRedirect
         *  - Set this to true if you want a click of the browser 'back' button to return the user to the state before this function was called.
         */
        loadFilteredContent(selectedTag, simulateRedirect) {
            // Note Regarding behaviour when all-checked/none-checked
            // ------------------------------------------------------
            // If all checkboxes are checked, we leave them that way.
            // We could have decided to uncheck all in this situation, to match the default state of all unchecked,
            // which behaves the same as if all were checked.  However, we decided it would be less confusing to the
            // user if checking the last checkbox resulted in all being checked rather than none being checked.

            this.selectedTag = selectedTag;

            if (simulateRedirect) {
                // Push the old filters state to the history object.
                history.pushState(this.getFiltersStateFromUrl(), "", window.location.href);
            }

            // Get the new filters state from the DOM, and create a new URL to match that state.
            let filtersState = this.getFiltersStateFromDom();
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
            this.load();

            // Replace the current state in the history object with the new state (and set the current URL to match that state).
            history.replaceState(filtersState, "", newUrl.toString());
        },

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
         *
         * @returns {?object} -
         * @see this.getFiltersStateFromDom()
         */
        getFiltersStateFromUrl() {
            let url = new URL(window.location.href);

            let filtersState = { tag: "", filterNames: [] };

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
        },

        /**
         * Generate a filterState object from the current state of the DOM.
         * Since the selectedTag is not stored in the DOM, we get that from this.selectedTag.
         *
         * Example of return value:
         *    {
         *       filterNames: ['feedback', 'learningMoments'], { Array of keys from this.selectedFilters }
         *       tag: '' { String }
         *    }
         *
         * @returns {object} -
         * @see this.getFiltersStateFromUrl()
         */
        getFiltersStateFromDom() {
            let checkedFeedItemTypes = [];

            for (const filterName of Object.keys(this.selectedFilters)) {
                if ($("#filter-" + filterName).prop("checked")) {
                    checkedFeedItemTypes.push(filterName);
                }
            }

            return { filterNames: checkedFeedItemTypes, tag: this.selectedTag };
        },

        /**
         * @param  {?object} filtersState -
         *
         * Example of non-null filtersState object:
         *    {
         *       filterNames: ['feedback', 'learningMoments'],
         *       tag: '' { String }
         *    }
         */
        setSelectedTagAndFiltersToMatchState(filtersState) {
            if (filtersState === null) {
                // Return the filters to their default state.
                this.selectedTag = "";

                for (let filterName of Object.keys(this.selectedFilters)) {
                    this.selectedFilters.filterName = false;
                }
            } else {
                // Restore state of selectedTag.
                this.selectedTag = filtersState.tag;

                // Restore state of filter checkboxes.
                for (const filterName of Object.keys(this.selectedFilters)) {
                    this.selectedFilters[filterName] = filtersState.filterNames.includes(filterName);
                }
            }
        },
    },
};
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
                                <template v-if="evidence.discriminator === 'evidence'">
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
                                                :href="evidence.object.creator._links.profile"
                                                :title="!evidence.object.creator.isDeleted ? 'View Profile' : null"
                                            >
                                                <img
                                                    :src="evidence.object.creator._links.avatar"
                                                    :alt="!evidence.object.creator.isDeleted ? 'View Profile' : null"
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
                                        <article v-if="evidence.object.filename && evidence.object.storage">
                                            <storage
                                                :storage="evidence.object.storage"
                                                :filename="evidence.object.filename"
                                                data-test="learning-moment-evidence-media"
                                            ></storage>
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
                                            :cdn-prefix="cdnPrefix"
                                            :site-version="siteVersion"
                                        />
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
                                                    :href="evidence.object.instructor._links.profile"
                                                    title="View Profile"
                                                    >{{ evidence.object.instructor.fullName }}</a
                                                >
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
                                            v-if="evidence.object.normalisedMark"
                                            :class="`grade ${gradient(evidence.object.normalisedMark)}`"
                                        >
                                            <span>{{ evidence.object.mark }}</span>
                                        </div>
                                    </div>
                                    <div class="content">
                                        <blockquote v-if="evidence.object.comment">
                                            {{ evidence.object.comment }}<br />
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

        <reveal-modal ref="nodeDetailsModal">
            <node-details :node="selectedNode" />
        </reveal-modal>

        <!-- TODO create a Vue equivalent of $.confirmDialog -->
        <reveal-modal ref="deleteModal">
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
                <li><a @click.prevent="$refs.deleteModal.close()" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>

        <award-detail-modal ref="awardDetailModal" :cdn-prefix="this.cdnPrefix" :site-version="this.siteVersion" />

        <toast ref="toast"></toast>
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
</style>
