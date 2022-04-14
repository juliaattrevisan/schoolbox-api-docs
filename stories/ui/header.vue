<script>

/**
 * Heading that contains most primary navigation elements
 */

export default {


    methods: {
       
        
    },

}

</script>

<template>
    <header>
       <nav class="header--primary">
            <ul>
                <li>
                    <a class="menu-icon">
                        <svg width="18" height="14" viewBox="0 0 80 60"><rect width="100" height="10"></rect> <rect width="100" y="25" height="10"></rect> <rect width="100" y="50" height="10"></rect></svg>
                    </a>
                </li>
                <!-- <li>
                    <a class="logo">
                        <img src="./img/logo.png" alt="Schoolbox Pty Ltd">
                    </a>
                </li> -->
                <li>
                    <a title="Navigate to your classes" class="icon-teacher">
                        <span>Classes</span>
                    </a>
                </li>
                <li>
                    <a>Timetable</a>
                </li>
                <li>
                    <a>Assessments</a>
                </li>
                <li>
                    <a>News</a>
                </li>
            </ul>
        </nav>
        <div class="header--secondary">
            
        </div>
    </header>
</template>

<style scoped>

header {
    align-items: center;
    /* background-color: var(--navigation-background); */
    background-color: yellow;
    color: white;
    display: flex;
    height: 4.5rem;
}

    .header--primary {
        flex-grow: 1;
    }

    .header--primary ul {
        display: flex;
        padding: 0;
    }

   .header--primary ul li {
        list-style-type: none;
    }

    .menu--icon {
        align-items: center;
        display: flex;
        justify-content: center;
        height: 4.5rem;
        width: 4.5rem;
        /* svg {
            fill: white;
        } */
    }

    .header--link {
        /* color: var(--body-foreground); */
        padding: 0.875rem 0;
        text-align: center;
        text-decoration: none;
    }

    .header--secondary {
        background-color: lightblue;
        flex: 0 0 144px;
    }

</style>