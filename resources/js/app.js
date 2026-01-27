import { Carousel } from "@fancyapps/ui/dist/carousel/";
import { Dots } from '@fancyapps/ui/dist/carousel/carousel.dots.js';
import { Autoplay } from '@fancyapps/ui/dist/carousel/carousel.autoplay.js';


Carousel(
  document.getElementById('ownerCarousel'),
  {
    // Your custom options
  },
  {
    // Autoplay,
    Dots,
  }
).init();

Carousel(
  document.getElementById('fitnessCarousel'),
  {
    // Your custom options
  },
  {
    // Autoplay,
    Dots,
  }
).init();
