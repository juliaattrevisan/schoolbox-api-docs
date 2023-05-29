# Slideshow

Used for all image slideshows on Schoolbox.

This is a wrapper around the image slideshow plugin we use - [Swiper](https://swiperjs.com/), with lazy loading and other preferred settings set by default.

## Initialising

You must have your HTML in the correct layout in order for this plugin to work.
```html
<div class="swiper" id="example-imageslider">
    <div class="swiper-wrapper">
        <div class="swiper-slide">
            <img src="/path/to/img/1" alt="" loading="lazy">
            <div class="loader"></div>
        </div>
        <div class="swiper-slide">
            <img src="/path/to/img/2" alt="" loading="lazy">
            <div class="loader"></div>
        </div>
        ...
    </div>
    <div class="swiper-pagination"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
</div>
<script src="{CDN_PREFIX}/js/bundle/slideshow.js"></script>
```

Then just initialise with JS like so:
```js
document.dispatchEvent(
    new CustomEvent("swiperInitialize", {
        detail: {
            target: document.getElementById("example-imageslider"),
            options: {},
        },
    })
);
````

## Styles
Styles can be found in `www/static/css/foundation/ui/__slideshow_.scss`.

## Parameters
You can send any of the options Swiper takes, see the [Swiper docs](https://swiperjs.com/swiper-api#parameters) for the list of Swiper parameters.

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
            <td>true</td>
            <td></td>
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
            <td>loopPreventsSliding</td>
            <td>false</td>
            <td></td>
        </tr>
        <tr>
            <td>lazyPreloaderClass</td>
            <td>loader</td>
            <td>Use our custom loader</td>
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
            <td>&nbsp;&nbsp;&nbsp;&nbsp;el</td>
            <td>.swiper-pagination</td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;type</td>
            <td>bullets</td>
            <td></td>
        </tr>
        <tr>
            <td>}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>navigation</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>{</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;nextEl</td>
            <td>.swiper-button-next</td>
            <td></td>
        </tr>
        <tr>
            <td>&nbsp;&nbsp;&nbsp;&nbsp;prevEl</td>
            <td>.swiper-button-prev</td>
            <td></td>
        </tr>
        <tr>
            <td>}</td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

## Known bugs & behaviors
- Previously lazy loading was handeled by us, however, the lazy loading API we used was removed as of SwiperJS v9.0.0 and it now relies on native browser lazy loading. Unfortuantely, this means the slider will no longer wait for the first image to finish loading before auto-playing.
- If you have a lot of images on the Image Slider on the Dashboard, you can get a boatload of navigation dots - we might consider hiding them if there's more than 10 or so images at which point navigation dots are pretty useless.
