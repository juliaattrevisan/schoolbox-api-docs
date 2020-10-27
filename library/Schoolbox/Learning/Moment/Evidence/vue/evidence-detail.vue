<script>
import moment       from "moment";
import MediaArticle from '@schoolbox/media-article';
import Storage      from '@schoolbox/storage';

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "evidence-detail",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
        'evidence'
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        MediaArticle,
        Storage
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            createdAt: null
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        this.createdAt = this.renderCreatedAt(this.evidence.createdAt);
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
        dateDisplayFormats: () => window.dateDisplayFormats
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Renders the created-at time as a string.
         *
         * @param {Moment} createdAt
         */
        renderCreatedAt(createdAt) {
            let now = moment().startOf('d'),
                dayDiff = moment(createdAt).diff(now, 'd');

            let struct = {
                'absolute': moment(createdAt).format(dateDisplayFormats.momentJsDatetime),
                'relative': null
            };

            if (dayDiff === 0) {
                struct.relative = 'today';
            } else if (dayDiff === -1) {
                struct.relative = 'yesterday';
            } else if (dayDiff === 1) {
                struct.relative = 'tomorrow';
            } else {
                struct.relative = moment.duration(dayDiff, 'd').humanize(true);
            }

            return struct;
        }
    }
}
</script>
<template>
    <div class="news-post row">
        <div class="small-12 island">
            <section>
                <div class="row">
                    <div class="small-12 island">
                        <section class="detail content">
                            <p class="meta">By
                                {{ this.evidence.recipient.fullName }}
                                &mdash;
                                <time :title="this.createdAt.absolute">
                                    {{ this.createdAt.relative }}
                                </time>
                            </p>

                            <!-- Display evidence body, with media players initialised -->
                            <media-article v-if="this.evidence.body">
                                <div v-html="this.evidence.body" />
                            </media-article>

                            <!-- Is there an attachment? Display it appropriately -->
                            <article v-if="this.evidence.filename && this.evidence.storage">
                                <storage :storage="this.evidence.storage" :filename="this.evidence.filename"></storage>
                            </article>
                        </section>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
