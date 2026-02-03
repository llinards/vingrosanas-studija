import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';
import {Autoplay} from '@fancyapps/ui/dist/carousel/carousel.autoplay.js';
import { Autoscroll } from "@fancyapps/ui/dist/carousel/carousel.autoscroll.js";

const galleryCarousel = document.getElementById('galleryCarousel');
const ownerCarousel = document.getElementById('ownerCarousel');
const workoutCarousel = document.getElementById('workoutCarousel');

if (galleryCarousel) {
    Carousel(galleryCarousel, {
        Autoplay: {
            pauseOnHover: false,
            showProgressbar: false,
            timeout: 3000,
        },
        gestures: false,
    }, {
        Autoplay,
        Dots,
    }).init();
}

if (ownerCarousel) {
    Carousel(ownerCarousel, {
        // Your custom options
    }, {
        Dots,
    }).init();
}


if (workoutCarousel) {
    Carousel(workoutCarousel, {
       gestures: false,
       infinite: true,
       Autoscroll: {
           autoStart: true,
           speed: 2,
       },
    }, {
    Autoscroll
    }).init();
}
