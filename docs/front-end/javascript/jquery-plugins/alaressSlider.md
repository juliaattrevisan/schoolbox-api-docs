# Alaress Slider

Used for all image slideshows on Schoolbox.

This is a wrapper around the image slider plugin we use - [Swiper](http://idangero.us/swiper/), with lazy loading and other preferred settings set by default.

## Initialising

You must have your HTML in the correct layout in order for this plugin to work.
```html
<div class="swiper-container" id="image-slider">
    <div class="swiper-wrapper">
        <div class="swiper-slide">
            <img data-src="/path/to/img/1" alt="" class="swiper-lazy"> <!-- data-src is used instead of src for lazy loading -->
            <div class="loader"></div>
        </div>
        <div class="swiper-slide">
            <img data-src="/path/to/img/2" alt="">
            <div class="loader"></div>
        </div>
        ...
    </div>
    <div class="swiper-button-prev" id="image-slider-button-prev"></div>
    <div class="swiper-button-next" id="image-slider-button-next"></div>
</div>
<div class="swiper-pagination" id="image-slider-pagination"></div>
```

Then just initialise with JS like so:
```js
$('#image-slider').alaressSlider({
    pagination: {
        el: '#image-slider-pagination'
    },
    navigation: {
        nextEl: '#image-slider-button-next',
        prevEl: '#image-slider-button-prev'
    }
});
````

## Styles
Styles can be found in `www/static/css/foundation/ui/_slider.scss`.

## Parameters
You can send any of the options Swiper takes, see the [Swiper docs](http://idangero.us/swiper/api/#parameters) for the list of Swiper parameters.

### Defaults

These are the Swiper parameters we set by default with this wrapper.

<table>
    <thead>
        <tr>
            <th>Parameter</th>
            <th>Default</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>autoplay</td>
            <td>false</td>
            <td>This is set to false by default, which is then overridden when the first image of the slider has finished loading. This way the user does not get slided through a bunch of loading graphics.</td>
        </tr>
        <tr>
            <td>centeredSlides</td>
            <td>true</td>
            <td></td>
        </tr>
        <tr>
            <td>loop</td>
            <td>true</td>
            <td></td>
        </tr>
        <tr>
            <td>speed</td>
            <td>1000</td>
            <td></td>
        </tr>
        <tr>
            <td>lazy</td>
            <td>true</td>
            <td></td>
        </tr>
        <tr>
            <td>{</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;loadPrevNext</td>
            <td>true</td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;loadPrevNextAmount</td>
            <td>3</td>
            <td>Load 3 images ahead</td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;preloaderClass</td>
            <td>loader</td>
            <td>Use our custom loader</td>
        </tr>
        <tr>
            <td>}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>pagination</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>{</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;clickable</td>
            <td>true</td>
            <td></td>
        </tr>
        <tr>
            <td>}</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>


### Custom Alaress Parameters

These are custom parameters we have created on this wrapper.

<table>
    <thead>
        <tr>
            <th>Parameter</th>
            <th>Type</th>
            <th>Default</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>alaress</td>
            <td>object</td>
            <td></td>
            <td>Just a wrapper for all the custom Alaress parameters, to make it clear which parameters are core Swiper parameters and which parameters are Alaress custom</td>
        </tr>
        <tr>
            <td>{</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;autoplayDelay</td>
            <td>number</td>
            <td>5000</td>
            <td>The autoplay delay that will be set once the first image has been loaded</td>
        </tr>
        <tr>
            <td>}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

## Lazy Loading
This wrapper has been created to do lazy loading by default. It will preload 3 images ahead. Some work has also been done to stop the slider from auto-playing until the first image has finished loading.


## Pagination tweaks
By default, this slider plugin places the pagination dots as an overlay on the bottom of the image (just like the next/prev arrows are also overlays).
However, this is not the schoolbox way of doing things: we prefer to have the pagination UNDERNEATH the entire slider, not overlaying the image.

In order to achieve this while maintaining the correct positioning of the next/prev arrows, the pagination has been moved to just below and outside of the `swiper-container` div.
The `.image-slider-pagination` css styles have also been moved outside of the `.swiper-container` in SASS to match the new HTML structure.

## Known bugs & behaviors
- If there is only a single image in a slideshow component, that image will loop. It actually duplicates the image so there are three (current, next and previous), causing this behavior. There may be an option that can be set to prevent this, but nothing I tried worked and we decided it wasn't essential to fix it since users are unlikely to create a slideshow with a single image and it's only a minor annoyance anyway.
- If you drag a slideshow component into a different column on a homepage, the sliding will stop, but can be "jump-started" by manually sliding.
- If you have a lot of images on the Image Slider on the Dashboard, you can get a boatload of navigation dots - we might consider hiding them if there's more than 10 or so images at which point navigation dots are pretty useless.
