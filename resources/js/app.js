import {Carousel} from '@fancyapps/ui';
import {Dots} from '@fancyapps/ui/dist/carousel/carousel.dots.js';
import {Autoplay} from '@fancyapps/ui/dist/carousel/carousel.autoplay.js';
import { Autoscroll } from "@fancyapps/ui/dist/carousel/carousel.autoscroll.js";
import Odometer from 'odometer';

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

const yearsOfExperience = document.getElementById('yearsOfExperience');
const totalCalories = document.getElementById('totalCalories');
const trainingCoaches = document.getElementById('trainingCoaches');
const totalClients = document.getElementById('totalClients');


const createOdometer = (el, value) => {
    const odometer = new Odometer({
        el: el,
        value: 0,
    });
    odometer.update(value)
};

createOdometer(yearsOfExperience, yearsOfExperience.innerHTML);
createOdometer(totalCalories, totalCalories.innerHTML);
createOdometer(trainingCoaches, trainingCoaches.innerHTML);
createOdometer(totalClients, totalClients.innerHTML);