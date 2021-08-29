<script>

/**
 * An individual student within the badge-report
 */

import BadgeImage from '../../vue/badge-image';

export default {
    
    components: {
        BadgeImage,
    },

    props: {
        student: { //contains all the data needed to display
            type: Object,
            required: true
        },
    },

}
</script>

<template>
    <li class="action-small-1 feed-item">
        <div class="card wrap-down">
            <a :href="`/search/user/${student.id}`">
                <img :src="`/portrait.php?id=${student.id}&size=square64`" :alt="student.fullName">
                {{ student.fullName }}
            </a>
        </div>
        <div v-for="(award, index) in student.awards" :key="index" class="left badge-card" style="width:100px; height:120px; overflow:hidden; text-align:center">
            <a href="#open-award-modal" @click.prevent="openDetailModal(award)" :title="award.badge.name">
                <badge-image :badge="award.badge" :css="award.isGoal ? 'goal' : ''"></badge-image>
                {{ award.badge.name }}
            </a>
        </div>
    </li>
</template>

<style lang="scss" scoped>

</style>