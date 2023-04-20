// import Swiper bundle with all modules installed
import Swiper from "swiper/bundle";
import { merge } from "lodash";

// import styles bundle
import "swiper/css/bundle";

const swiperDefaults = {
    autoplay: true,
    centeredSlides: true,
    loop: true,
    loopPreventsSliding: false,
    lazyPreloaderClass: "loader",
    pagination: {
        clickable: true,
        el: ".swiper-pagination",
        type: "bullets",
    },
    navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
    },
};

// Prevent creating multiple event listeners
if (window.sbProps.imageSlideshows === undefined) {
    window.sbProps.imageSlideshows = [];

    document.addEventListener("swiperInitialize", (e) => {
        const swiperEl = e.detail.target;

        // Prevent multiple initializations
        if (swiperEl.swiper !== undefined) {
            return;
        }

        const swiperImages = swiperEl.querySelectorAll("img");
        swiperImages.forEach((image) => {
            // If has [data-max-height] attribute, set max-height and object-fit
            if (!image.dataset.maxHeight || image.dataset.maxHeight <= 0) {
                return;
            }
            image.style.maxHeight = image.dataset.maxHeight + "px";
            image.style.objectFit = "contain";
        });

        const options = merge(swiperDefaults, e.detail.options);
        const swiper = new Swiper(swiperEl, options);

        window.sbProps.imageSlideshows.push(swiper);
    });
}
