<script setup>

/**
 * 
 */

import HeaderTopBar from './header-top-bar';


function onOpenSideMenu () {
    console.warn('onOpenSideMenu');
}

function onToggleProfileMenu () {
    console.warn('onToggleProfileMenu');
}

</script>

<template>
   <header>
       <header-top-bar 
            @openSideMenu="onOpenSideMenu"
            @toggleProfileMenu="onToggleProfileMenu"
       />
   </header>
</template>

<style scoped>

</style>