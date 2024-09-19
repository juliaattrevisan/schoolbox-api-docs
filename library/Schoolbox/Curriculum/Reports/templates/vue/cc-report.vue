<script setup lang="ts">
/**
 * Displays the curriculum coverage report in a table format.
 */
</script>

<template>
    <div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Curriculum</th>
                    <!-- colspan will be the number of units plus total -->
                    <th colspan="4">Year 9 English</th>
                </tr>
                <tr>
                    <th>Romeo and Juliet</th>
                    <th>Persuasion and the Media</th>
                    <th>To Kill a Mockingbird</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Chris</th>
                    <td>1</td>
                    <td>2</td>
                    <td>2</td>
                    <td>5</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped></style>
