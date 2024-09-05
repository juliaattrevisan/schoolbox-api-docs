<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { getFormData } from "@js/util/queryUtils";
import toastMixin from "@schoolboxVue/components/toast-mixin";
import BadgeImage from "../../vue/badge-image.vue";

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "award-withdraw-modal",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [toastMixin],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RevealModal,
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            award: {},
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {},

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {},

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

        /**
         * Close this modal
         */
        close() {
            this.$refs.modal.close();
        },

        /**
         * Perform withdrawal
         */
        withdraw() {
            let self = this;

            /* A toast - to your valour, my sword, and our victory together
             */
            self.toastActivate("Removing goal...");

            fetch(this.award._links.goalWithdraw, {
                method: "POST",
                body: getFormData({ userIds: [this.award.recipient.id] }),
            })
                .then((response) => {
                    if (response.ok) {
                        /* Send bat signal to the parent component
                         */
                        this.$emit("withdraw-complete");

                        /* Close this modal
                         */
                        self.close();

                        /* Pop toast
                         */
                        self.toastSuccess("Goal has been removed");
                    } else {
                        self.toastError("Unable to remove goal; please try again.");
                    }
                })
                .catch((error) => {
                    if (error.response) {
                        self.toastError("Unable to remove goal; please try again.");
                    }
                });
        },
    },
};
</script>
<template>
    <reveal-modal ref="modal">
        <template v-if="award.badge">
            <h2>
                Remove <strong>'{{ award.badge.name }}'</strong> goal
            </h2>

            <div class="card small-12 compact badge-card">
                <div>
                    <badge-image :badge="award.badge"></badge-image>
                    <h3>{{ award.badge.name }}</h3>
                </div>
            </div>

            <p>Are you sure you want to remove this goal?</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="withdraw()" class="button submit">Remove</a></li>
                <li><a @click.prevent="close()" class="button">Cancel</a></li>
            </ul>
        </template>
    </reveal-modal>
</template>
