<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Adds dropzone functionality to the table
 * References www/static/js/resource/jquery.fileDrop.js
 */

import { gsap } from "gsap/all";

export default {
    props: {
        dropzoneSelector: {
            //the dom selector to assign fileDrop.js to
            type: String,
            required: true,
        },
        sourceFolderId: {
            //to know where to upload files to
            type: Number,
            required: true,
        },
    },

    methods: {
        /**
         * To be able to drag and drop files straight onto the table
         */
        initDropzone() {
            $(this.dropzoneSelector).fileDrop({
                previewsContainer: "[data-dz-container]",
                previewTemplate: "[data-dz-template]",
                reloadContent: false,
                sending: (file, xhr, formData) => {
                    this.appendFormData(file, formData);
                },
                success: (file, response) => {
                    this.displayFileUploadSuccess(file);
                    this.$emit("on-file-uploaded", response);
                },
                error: (file, response) => {
                    this.displayFileUploadError(file, response);
                },
            });
        },

        /**
         * Add the extra properties required for the /fileForm.php endpoint
         *
         * @param { Object }
         * @param { FormData }
         */
        appendFormData(file, formData) {
            const fileDescription = file.name.substr(0, file.name.lastIndexOf("."));
            const uploadDescription = fileDescription.length > 0 ? fileDescription : file.name; //ensure there's a file name, otherwise just add the extension
            formData.append("description", uploadDescription);
            formData.append("category", this.sourceFolderId);
            formData.append("comment", "");
            formData.append("hidden", 0);
        },

        /**
         * Update the display to notify of success and animate it out
         *
         * @param { Object }
         */
        displayFileUploadSuccess(file) {
            const previewElement = file.previewElement;
            previewElement.classList.remove("dz-processing");
            previewElement.querySelector("[data-dz-thumbnail]").setAttribute("src", `/ajax/mime/${file.type}`);
            previewElement.querySelector("span.dz-upload").classList.add("complete");
            previewElement.querySelector("[data-dz-status]").textContent = "Success";
            previewElement.style.overflow = "hidden";
            gsap.to(previewElement, {
                height: 0,
                delay: 1,
                duration: 0.5,
                onComplete: () => previewElement.remove(),
            });
        },

        /**
         * Can't upload the file, so notify the reason and show the close button
         *
         * @param { Object }
         * @param { Object }
         */
        displayFileUploadError(file, response) {
            this.$el.querySelector(".dz-error [data-close]").classList.remove("hide");
            file.previewElement.classList.remove("dz-processing");
            const errorMessage = response.error ? response.error : response; //the error message can be in either place
            file.previewElement.querySelector("[data-dz-status]").textContent = errorMessage;
        },
    },
};
</script>

<template>
    <div class="dropped-files-container">
        <div data-dz-container=""></div>
        <div data-dz-template="" class="hide">
            <div data-dz-template-item>
                <div class="card small-12">
                    <button class="close-button hide" aria-label="Close alert" type="button" data-close="">
                        <span aria-hidden="true">×</span>
                    </button>
                    <img
                        data-dz-thumbnail=""
                        class="mime"
                        :src="`${cdnPrefix}/images/foundation/preloader.gif`"
                        alt="file loading spinner"
                    />
                    <p data-dz-name=""></p>
                    <p class="meta" data-dz-status="">Uploading...</p>
                    <div class="progress dz-progress">
                        <span class="meter dz-upload" style="width: 0"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
.dropped-files-container {
    background: white;
    ul li {
        list-style-type: none;
    }
    .card {
        background: white;
    }
}

.close-button {
    //move to be more inline with the edit icons
    right: 0.8rem;
    top: 0.25rem;
}
</style>
