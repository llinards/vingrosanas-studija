import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';
import {Autoplay} from '@fancyapps/ui/dist/carousel/carousel.autoplay.js';
import { Autoscroll } from "@fancyapps/ui/dist/carousel/carousel.autoscroll.js";
import Odometer from 'odometer';

document.addEventListener('DOMContentLoaded', () => {
    const galleryCarousel = document.getElementById('galleryCarousel');
    const ownerCarousel = document.getElementById('ownerCarousel');
    const workoutCarousel = document.getElementById('workoutCarousel');
    const coachCarousel = document.getElementById('coachCarousel');

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
            Autoscroll,
        }).init();
    }

    if (coachCarousel) {
        Carousel(coachCarousel, {
            fill: true,
            infinite: false,
        }, {
        }).init();
    }


const createOdometer = (el, value) => {
    const odometer = new Odometer({
        el: el,
        value: 0,
    });
    odometer.update(value)
};


const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.dataset.odometerInitialized = '1';
            observer.unobserve(entry.target);

            createOdometer(entry.target, entry.target.textContent)
        }
    });
}, { threshold: 0.6 })

const odometerNumbers = document.querySelectorAll('.counter-number')
odometerNumbers.forEach((el) => observer.observe(el));

});

const contactForm = document.getElementById('contactForm');
contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    Flux.modal('confirm').show()
})