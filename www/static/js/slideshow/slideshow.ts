// import Swiper bundle with all modules installed
import Swiper from 'swiper/bundle';

// import styles bundle
import 'swiper/css/bundle';

const swiperDefaults = {
    autoplay: {
        disableOnInteraction: false,
    },
    centeredSlides: true,
    loop: true,
    loopPreventsSliding: false,
    lazyPreloaderClass: 'loader',
    pagination: {
        clickable: true,
        el: '.swiper-pagination',
        type: 'bullets',
    },
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    }
}

// Prevent creating multiple event listeners
if (window.sbProps.imageSlideshows === undefined) {
    window.sbProps.imageSlideshows = [];

    document.addEventListener('swiperInitialize', (e) => {
        const swiperEl = e.detail.target;

        // Prevent multiple initializations
        if (swiperEl.swiper !== undefined) { return; }

        const swiperImages = swiperEl.querySelectorAll('img');
        swiperImages.forEach((image) => {
            // If has [data-max-height] attribute, set max-height and object-fit
            if (!image.dataset.maxHeight) { return; }
            if (image.dataset.maxHeight <= 0) { return; }
            image.style.maxHeight = image.dataset.maxHeight + 'px';
            image.style.objectFit = 'contain';
        });

        const options = mergeObjects(swiperDefaults, e.detail.options);
        const swiper = new Swiper(swiperEl, options);

        window.sbProps.imageSlideshows.push(swiper);
    });
}

// Merge two objects together and return the result
// This function will merge nested objects recursively, such that properties inside nested objects will be merged
// Compared to Object.assign, this function will not overwrite properties in the target object
function mergeObjects(target, source) {
    // Create a new object that contains the properties of both the target and source objects
    const mergedObject = {...target, ...source};

    // Iterate over all the properties in the source object
    Object.keys(source).forEach(key => {
        // If the key exists in both the target and source objects and is an object, merge the nested objects recursively
        if (key in target && typeof target[key] === 'object' && typeof source[key] === 'object') {
            mergedObject[key] = mergeObjects(target[key], source[key]);
        }
    });

    return mergedObject;
}
