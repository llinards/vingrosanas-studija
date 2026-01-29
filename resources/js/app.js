import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';
import {Autoplay} from '@fancyapps/ui/dist/carousel/carousel.autoplay.js';

const galleryCarousel = document.getElementById('galleryCarousel');
const ownerCarousel = document.getElementById('ownerCarousel');

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
