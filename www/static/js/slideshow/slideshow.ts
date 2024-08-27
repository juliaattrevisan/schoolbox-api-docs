// import Swiper bundle with all modules installed
import Swiper from "swiper/bundle";
import { merge } from "lodash";
import type { SwiperOptions } from "node_modules/swiper/types/swiper-options";

// import styles bundle
import "swiper/css/bundle";

const swiperDefaults: SwiperOptions = {
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

interface SwiperInitializeEvent extends CustomEvent {
    detail: {
        target: HTMLElement;
        options: SwiperOptions;
    };
}

// Prevent creating multiple event listeners
if (window.sbProps.imageSlideshowListenerExists === undefined) {
    window.sbProps.imageSlideshowListenerExists = true;

    document.addEventListener("swiperInitialize", (e) => {
        const eventDetail = (e as SwiperInitializeEvent).detail;
        const swiperEl = eventDetail.target;

        // Prevent multiple initializations
        if (swiperEl.getAttribute("data-swiper-initialized") === "true") return;
        swiperEl.setAttribute("data-swiper-initialized", "true");

        const swiperImages = swiperEl.querySelectorAll("img");
        swiperImages.forEach((image) => {
            // If has [data-max-height] attribute, set max-height and object-fit
            if (!image.dataset.maxHeight || parseInt(image.dataset.maxHeight, 10) <= 0) {
                return;
            }
            image.style.maxHeight = `${image.dataset.maxHeight}px`;
            image.style.objectFit = "contain";
        });

        const options = merge(swiperDefaults, eventDetail.options);
        // eslint-disable-next-line no-new -- Swiper requires new to trigger initialisation
        new Swiper(swiperEl, options);
    });
}
