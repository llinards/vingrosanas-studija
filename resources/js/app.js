import { Carousel } from "@fancyapps/ui";
import { Dots } from "@fancyapps/ui/dist/carousel/carousel.dots.js";
import { Autoplay } from "@fancyapps/ui/dist/carousel/carousel.autoplay.js";
import { Autoscroll } from "@fancyapps/ui/dist/carousel/carousel.autoscroll.js";

document.addEventListener("DOMContentLoaded", () => {
 
    const galleryCarousel = document.getElementById("galleryCarousel");
    const ownerCarousel = document.getElementById("ownerCarousel");
    const workoutCarousel = document.getElementById("workoutCarousel");
    const coachCarousel = document.getElementById("coachCarousel");

    if (galleryCarousel) {
        Carousel(
            galleryCarousel,
            {
                Autoplay: {
                    pauseOnHover: false,
                    showProgressbar: false,
                    timeout: 3000,
                },
            },
            {
                Autoplay,
                Dots,
            },
        ).init();
    }

    if (ownerCarousel) {
        Carousel(
            ownerCarousel,
            {
                Autoplay: {
                    pauseOnHover: false,
                    showProgressbar: false,
                    timeout: 3000,
                },
            },
            {
                Autoplay,
                Dots,
            },
        ).init();
    }

    if (workoutCarousel) {
        Carousel(
            workoutCarousel,
            {
                gestures: false,
                infinite: true,
                Autoscroll: {
                    autoStart: true,
                    speed: 2,
                },
            },
            {
                Autoscroll,
            },
        ).init();
    }

    if (coachCarousel) {
        Carousel(
            coachCarousel,
            {
                fill: true,
                infinite: false,
            },
            {},
        ).init();
    }


});
