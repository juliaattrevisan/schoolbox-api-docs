<script>
import goalMixin from './goal-mixin';
import RelativeTime from '@schoolboxLearningMomentEvidence/relative-time';
import RevealModal from '@schoolbox/reveal-modal'

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
        goalMixin
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
        }
    }
}
</script>
<template>
    <reveal-modal ref="modal">
        <template v-if="award.badge">
            <div class="v-card-list awards">
                <div class="v-card card wrap-down">
                    <div class="card-feature-image">
                        <img :src="award.badge.image._links.image + '&size=constrain200'" :class="getCss(award)"/>
                    </div>
                    <div class="card-content">
                        <h3 class="no-margin">{{ award.badge.name }} ({{ award.count }})</h3>
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

                        <hr />

                        <article v-html="award.badge.body"></article>

                        <template v-if="award.badge.curriculumNodes.length">
                            <hr />

                            <p class="meta">
                                <template v-for="curriculumNode in award.badge.curriculumNodes">
                                    {{ curriculumNode.code }}
                                </template>
                            </p>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </reveal-modal>
</template>
