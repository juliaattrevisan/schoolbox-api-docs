<template>
    <div>
        <reveal-modal ref="modal">
            <template v-if="isGroupTemplate">
                <div class="small-12 medium-6 columns no-pad" v-if="groups.length">
                    <p><strong>Groups</strong></p>
                    <ul>
                        <li v-for="group in groups" :key="group.id">
                            <a :href="/homepage/ + group.id">{{ group.name }}</a>
                        </li>
                    </ul>
                </div>
            </template>
            <template v-else>
                <div class="small-12 medium-6 columns no-pad" v-if="campuses.length">
                    <p>
                        <strong>{{ campusesLabel }}</strong>
                    </p>
                    <ul>
                        <li v-for="(campus, index) in campuses" :key="index">
                            {{ campus.name }}
                        </li>
                    </ul>
                </div>
                <div class="small-12 medium-6 columns no-pad" v-if="roles.length">
                    <p>
                        <strong>{{ rolesLabel }}</strong>
                    </p>
                    <ul>
                        <li v-for="(role, index) in roles" :key="index">
                            {{ role.name }}
                        </li>
                    </ul>
                </div>
            </template>
        </reveal-modal>
    </div>
</template>

<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";

export default {
    name: "audiences-modal",

    props: ["campus-audiences", "role-audiences", "group-audiences", "is-group"],

    components: { RevealModal },

    data() {
        return {
            campuses: this.campusAudiences,
            roles: this.roleAudiences,
            groups: this.groupAudiences,
            isGroupTemplate: this.isGroup,
        };
    },

    computed: {
        campusesLabel: function () {
            return this.campuses.length > 1 ? "Campuses" : "Campus";
        },

        rolesLabel: function () {
            return this.roles.length > 1 ? "Roles" : "Role";
        },
    },

    methods: {
        open() {
            this.$refs.modal.open();
        },
    },
};
</script>
