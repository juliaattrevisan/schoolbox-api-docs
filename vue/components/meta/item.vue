<template>
    <li>
        <sbx-icon v-if="icon" :name="icon" color="var(--sbx-color-text-secondary)"></sbx-icon>
        <slot></slot>
    </li>
</template>

<script>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
export default {
    name: "meta-item",

    props: {
        icon: {
            type: String,
            default: "",
        },
    },
};
</script>
