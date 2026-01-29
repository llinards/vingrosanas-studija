import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';

const galleryCarousel = document.getElementById('galleryCarousel');
const ownerCarousel = document.getElementById('ownerCarousel');

if (galleryCarousel) {
    Carousel(galleryCarousel, {
        // Your custom options
    }, {
        Dots,
    }).init();
}

if (ownerCarousel) {
    Carousel(ownerCarousel, {
        // Your custom options
    }, {
        Autoplay: false,
        Dots,
    }).init();
}
