import { Carousel } from "@fancyapps/ui/dist/carousel/";
import { Dots } from '@fancyapps/ui/dist/carousel/carousel.dots.js';

Carousel(
  document.getElementById('ownerCarousel'),
  {
    // Your custom options
  },
  {
    Dots,
  }
).init();
