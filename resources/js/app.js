import { Carousel } from "@fancyapps/ui";
import { Dots } from "@fancyapps/ui/dist/carousel/carousel.dots.js";
import { Autoplay } from "@fancyapps/ui/dist/carousel/carousel.autoplay.js";
import { Autoscroll } from "@fancyapps/ui/dist/carousel/carousel.autoscroll.js";
import Odometer from "odometer";

document.addEventListener("DOMContentLoaded", () => {
    const isFinePointer = window.matchMedia(
        "(hover: hover) and (pointer: fine)",
    ).matches;

    if (isFinePointer) {
        const cursor = document.createElement("div");
        cursor.className = "custom-cursor";
        cursor.setAttribute("aria-hidden", "true");
        // Use popover API to place cursor in the top layer so it appears above native dialogs
        if (cursor.popover !== undefined) {
            cursor.popover = "manual";
        }
        document.body.appendChild(cursor);

        if (cursor.showPopover) {
            cursor.showPopover();
        }

        let mouseX = 0;
        let mouseY = 0;
        let rafId = null;
        let isActive = false;

        const render = () => {
            rafId = null;
            cursor.style.transform = `translate3d(${mouseX}px, ${mouseY}px, 0) translate(-50%, -50%)`;
        };

        // Ensure cursor stays on top of new modals
        document.addEventListener(
            "toggle",
            (e) => {
                if (
                    e.target.tagName === "DIALOG" &&
                    e.target.open &&
                    cursor.showPopover
                ) {
                    cursor.hidePopover();
                    cursor.showPopover();
                }
            },
            { capture: true },
        );

        window.addEventListener(
            "mousemove",
            (event) => {
                if (!isActive) {
                    isActive = true;
                    document.body.classList.add("custom-cursor-active");
                    cursor.style.opacity = "1";
                }

                mouseX = event.clientX;
                mouseY = event.clientY;

                if (rafId === null) {
                    rafId = window.requestAnimationFrame(render);
                }
            },
            { passive: true },
        );
    }

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

    const contactForm = document.getElementById("contactForm");
    if (coachCarousel) {
        contactForm.addEventListener("submit", (e) => {
            e.preventDefault();
            Flux.modal("confirm").show();
        });
    }
});
