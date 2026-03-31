<aside id="cookies-policy" class="cookies cookies--no-js"
       data-text="{{ json_encode(__('cookieConsent::cookies.details')) }}">
    <div class="cookies__alert">
        <div class="cookies__container">
            <div class="cookies__wrapper">
                <flux:heading level="3" class="mb-2">@lang('cookieConsent::cookies.title')</flux:heading>
                <div class="cookies__intro">
                    <flux:text>@lang('cookieConsent::cookies.intro')</flux:text>
                    @if($policy)
                        <flux:text class="mt-1">@lang('cookieConsent::cookies.link', ['url' => $policy])</flux:text>
                    @endif
                </div>
                <div class="cookies__actions">
                    @cookieconsentbutton(action: 'accept.essentials', label: __('cookieConsent::cookies.essentials'), attributes: ['class' => 'cookiesBtn cookiesBtn--essentials btn btn-sm btn-tertiary'])
                    @cookieconsentbutton(action: 'accept.all', label: __('cookieConsent::cookies.all'), attributes: ['class' => 'cookiesBtn cookiesBtn--accept btn btn-sm btn-primary'])
                </div>
            </div>
        </div>
        <a href="#cookies-policy-customize" class="cookies__btn cookies__btn--customize">
            <span>@lang('cookieConsent::cookies.customize')</span>
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"
                 aria-hidden="true">
                <path
                    d="M14.7559 11.9782C15.0814 11.6527 15.0814 11.1251 14.7559 10.7996L10.5893 6.63297C10.433 6.47669 10.221 6.3889 10 6.38889C9.77899 6.38889 9.56703 6.47669 9.41075 6.63297L5.24408 10.7996C4.91864 11.1251 4.91864 11.6527 5.24408 11.9782C5.56951 12.3036 6.09715 12.3036 6.42259 11.9782L10 8.40074L13.5774 11.9782C13.9028 12.3036 14.4305 12.3036 14.7559 11.9782Z"
                    fill="currentColor"/>
            </svg>
        </a>
        <div class="cookies__expandable cookies__expandable--custom" id="cookies-policy-customize">
            <form action="{{ route('cookieconsent.accept.configuration') }}" method="post" class="cookies__customize">
                @csrf
                <div class="cookies__sections">
                    @foreach($cookies->getCategories() as $category)
                        <div class="cookies__section">
                            <label for="cookies-policy-check-{{ $category->key() }}" class="cookies__category">
                                @if ($category->key() === 'essentials')
                                    <input type="hidden" name="categories[]" value="{{ $category->key() }}"/>
                                    <input type="checkbox" name="categories[]" value="{{ $category->key() }}"
                                           id="cookies-policy-check-{{ $category->key() }}" checked="checked"
                                           disabled="disabled"/>
                                @else
                                    <input type="checkbox" name="categories[]" value="{{ $category->key() }}"
                                           id="cookies-policy-check-{{ $category->key() }}"/>
                                @endif
                                <span class="cookies__box">
                                    <strong class="cookies__label">{{ $category->title }}</strong>
                                </span>
                                @if($category->description)
                                    <flux:text class="cookies__info">{{ $category->description }}</flux:text>
                                @endif
                            </label>

                            <div class="cookies__expandable" id="cookies-policy-{{ $category->key() }}">
                                <ul class="cookies__definitions">
                                    @foreach($category->getCookies() as $cookie)
                                        <li class="cookies__cookie">
                                            <p class="cookies__name">{{ $cookie->name }}</p>
                                            <p class="cookies__duration">{{ Carbon\Carbon::now()->diffForHumans(Carbon\Carbon::now()->addMinutes($cookie->duration), true) }}</p>
                                            @if($cookie->description)
                                                <p class="cookies__description">{{ $cookie->description }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <a href="#cookies-policy-{{ $category->key() }}"
                               class="cookies__details">@lang('cookieConsent::cookies.details.more')</a>
                        </div>
                    @endforeach
                </div>
                <div class="cookies__save">
                    <button type="submit" class="cookiesBtn__link btn btn-sm btn-primary">@lang('cookieConsent::cookies.save')</button>
                </div>
            </form>
        </div>
    </div>
</aside>

<script data-cookie-consent>
    {!! file_get_contents(LCC_ROOT . '/dist/script.js') !!}
</script>
<style data-cookie-consent>
    /* ============================================================================
       Cookie Consent - Custom styles matching site design system
       ========================================================================== */

    /* Base: hidden until JS removes cookies--no-js */
    #cookies-policy.cookies--no-js { display: none; }

    /* Banner positioning */
    #cookies-policy {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 50;
        padding: 1rem;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    @media (min-width: 768px) {
        #cookies-policy { padding: 1.5rem; }
    }

    #cookies-policy.cookies--closing {
        opacity: 0;
        transform: translateY(1rem);
        pointer-events: none;
    }

    /* Alert container */
    .cookies__alert {
        max-width: 42rem;
        margin: 0 auto;
        background-color: var(--color-body);
        border: 1px solid var(--color-surface);
        border-radius: 1rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    /* Main content wrapper — keep always visible, don't collapse on customize */
    .cookies__container {
        overflow: hidden;
        height: auto !important;
    }

    .cookies__container--hide { pointer-events: auto; }

    .cookies__wrapper {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    @media (min-width: 768px) {
        .cookies__wrapper { padding: 2rem; }
    }

    /* Intro text */
    .cookies__intro { display: flex; flex-direction: column; gap: 0.25rem; }
    .cookies__intro a {
        color: var(--color-accent-content);
        text-decoration: underline;
        text-underline-offset: 6px;
        text-decoration-color: color-mix(in oklab, var(--color-accent-content), transparent 80%);
        transition: all 0.3s ease;
    }
    .cookies__intro a:hover { color: var(--color-blue); text-decoration-color: currentColor; }

    /* Action buttons */
    .cookies__actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    @media (min-width: 640px) {
        .cookies__actions { flex-direction: row; }
    }

    .cookiesBtn {
        display: contents;
    }

    .cookiesBtn button {
        font-family: var(--font-heading);
        font-weight: 600;
        border-width: 2px;
        letter-spacing: 0.025em;
        transition: all 0.3s ease;
        cursor: pointer;
        text-transform: uppercase;
        border-radius: 1.5rem;
        padding: 1rem 1.25rem;
        font-size: 1rem;
        text-align: center;
    }

    @media (min-width: 768px) {
        .cookiesBtn button {
            padding: 1.25rem 1.5rem;
            font-size: 1.125rem;
        }
    }

    .cookiesBtn--accept button {
        background-color: var(--color-blue);
        border-color: var(--color-blue);
        color: white;
    }

    .cookiesBtn--accept button:hover { opacity: 0.7; }

    .cookiesBtn--essentials button {
        background-color: transparent;
        border-color: var(--color-blue);
        color: var(--color-blue);
    }

    .cookiesBtn--essentials button:hover {
        border-color: black;
        color: black;
    }

    /* Customize toggle */
    .cookies__btn--customize {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-family: var(--font-heading);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-gray);
        border-top: 1px solid black;
        cursor: pointer;
        transition: color 0.3s ease;
        text-decoration: none;
    }

    .cookies__btn--customize:hover { color: var(--color-blue); }

    .cookies__btn--customize svg {
        width: 1rem;
        height: 1rem;
        transition: transform 0.3s ease;
    }

    .cookies--show .cookies__btn--customize svg { transform: rotate(180deg); }

    /* Expandable panels */
    .cookies__expandable {
        height: 0;
        overflow: hidden;
        transition: height 0.3s ease;
    }

    .cookies__expandable--open { height: auto; }

    /* Customize form */
    .cookies__customize {
        padding: 0 1.5rem 1.5rem;
    }

    @media (min-width: 768px) {
        .cookies__customize { padding: 0 2rem 2rem; }
    }

    .cookies__sections {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    /* Category sections — matching accordion style */
    .cookies__section {
        border-bottom: 1px solid black;
        padding: 0.75rem 0;
    }

    .cookies__section:first-child {
        border-top: 1px solid black;
    }

    .cookies__category {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
    }

    /* Checkbox styling */
    .cookies__category input[type="checkbox"] {
        appearance: none;
        width: 1.25rem;
        height: 1.25rem;
        border: 2px solid var(--color-blue);
        border-radius: 0.25rem;
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .cookies__category input[type="checkbox"]:checked {
        background-color: var(--color-blue);
        border-color: var(--color-blue);
    }

    .cookies__category input[type="checkbox"]:checked::after {
        content: '';
        position: absolute;
        left: 0.3rem;
        top: 0.1rem;
        width: 0.35rem;
        height: 0.6rem;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }

    .cookies__category input[type="checkbox"]:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .cookies__box { flex-shrink: 0; }

    .cookies__label {
        font-family: var(--font-heading);
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        color: var(--color-gray);
    }

    .cookies__info {
        width: 100%;
        flex-basis: 100%;
    }

    /* Cookie details toggle */
    .cookies__details {
        display: inline-block;
        margin-top: 0.5rem;
        color: var(--color-accent-content);
        text-decoration: underline;
        text-underline-offset: 6px;
        text-decoration-color: color-mix(in oklab, var(--color-accent-content), transparent 80%);
        transition: color 0.3s ease;
    }

    .cookies__details:hover { color: var(--color-blue); text-decoration-color: currentColor; }

    /* Cookie definitions list */
    .cookies__definitions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding-top: 0.75rem;
        margin-top: 0.75rem;
        border-top: 1px solid var(--color-surface);
        list-style: none;
        padding-left: 0;
    }

    .cookies__cookie {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem 0.75rem;
        font-size: 0.875rem;
    }

    .cookies__name {
        font-family: var(--font-body);
        font-weight: 600;
        color: var(--color-gray);
        flex-shrink: 0;
    }

    .cookies__duration { color: #a3a3a3; }
    .cookies__description { color: #737373; width: 100%; }

    /* Save button */
    .cookies__save {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
    }
</style>
