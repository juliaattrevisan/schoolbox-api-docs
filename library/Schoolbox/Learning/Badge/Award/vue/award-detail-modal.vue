<script>
import BadgeImage from './../../vue/badge-image';
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import RevealModal from '@schoolbox/reveal-modal'
import axios from "axios";
import toastMixin from '@schoolbox/toast-mixin';

export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "award-detail-modal",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        goalMixin,
        toastMixin,
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RelativeTime,
        RevealModal
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            award: {}
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Open this modal.
         */
        open(award) {
            this.award = award;

            this.$refs.modal.open();
        },

        awardAchievement () {
            // Post to goalAward
            let self  = this,
                award = this.award,
                request = {
                    url: award._links.goalAward,
                    data: {users: [award.recipient]},
                    method: 'post',
                    responseType: 'json'
                };

            self.toastActivate('Awarding...');
            axios
                .request(request)
                .then(() => {
                    self.toastSuccess('Achievement awarded');

                    /* Temporarily replaced implementation of self.updateAwards()
                     * achievement-feed.vue can't tell goal-feed.vue to update itself; they don't
                     * share a common parent. They're entirely separate vue apps. An action inside
                     * achievement-feed affects the data in goal-feed, but I can't talk to
                     * goal-feed from achievement-feed.
                     *
                     * The forced reload will cause goal-feed to seem updated, for now
                     */
                    // window.location.reload();
                })
                .catch(() => self.toastError('Failed to award achievement'))
        }
    }
}
</script>
<template>
    <reveal-modal ref="modal" size="medium">
        <template v-if="award.badge">
            <div class="badge-modal">
                <div class="badge-img-container">
                    <badge-image :badge="award.badge" :css="getCss(award)"></badge-image>
                    <template v-if="award.count > 1">
                        <div class="award-count" :title="'Awarded ' + award.count + ' times'">x{{ award.count }}</div>
                    </template>
                </div>
                <!-- Only show the count if one was provided in the award -->
                <h2>{{ award.badge.name }}</h2>
                <p class="meta">Category: {{ award.badge.category.name }}</p>
                <p class="meta">
                    <template v-if="award.isGoal">
                        Goal set <relative-time :datetime="award.awardedAt"></relative-time> by
                        <a :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a>
                    </template>
                    <template v-else>
                        Awarded <relative-time :datetime="award.awardedAt"></relative-time> by
                        <a :href="award.awardedBy._links.profile">{{ award.awardedBy.fullName }}</a>
                    </template>
                </p>

                <article v-html="award.badge.body"></article>

                <template v-if="award.badge.curriculumNodes.length">
                    <hr />

                    <p class="meta">
                        Curriculum Codes:
                        <template v-for="curriculumNode in award.badge.curriculumNodes">
                            {{ curriculumNode.code }}
                        </template>
                    </p>
                </template>

                <div class="component-action">
                    <section>
                        <a v-if="award._links.goalAward && award.isGoal"
                           class="button"
                           @click.prevent="awardAchievement">Award Achievement</a>
                    </section>
                </div>
            </div>
        </template>
    </reveal-modal>
</template>
