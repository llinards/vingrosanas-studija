<div>
    <div class="f-carousel full-width-banner" id="galleryCarousel">
        @foreach (json_decode(site('media', 'gallery_images', '[]'), true) ?: [] as $image)
            <div class="f-carousel__slide">
                <img src="{{ asset($image) }}" alt="">
            </div>
        @endforeach
    </div>
</div>