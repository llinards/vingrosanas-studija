import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';

const fitnessCarousel = document.getElementById('fitnessCarousel');
const ownerCarousel = document.getElementById('ownerCarousel');

if (fitnessCarousel) {
    Carousel(fitnessCarousel, {
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
