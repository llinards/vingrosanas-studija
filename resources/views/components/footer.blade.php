<footer class="container mx-auto px-4">
    <div
        class="flex flex-col md:flex-row text-center md:text-right items-center justify-between border-y border-black py-3">
        <div class="w-36">
            <img src="{{ asset('images/vingrosanas_studija_logo.svg') }}" alt="">
        </div>
        <flux:heading level="2">VINGROŠANAS
            STUDIJA
            veselīgam
            dzīvesveidam</flux:heading>
    </div>

    <div class="pt-12 pb-12 md:flex md:flex-row space-y-6 md:space-y-0 gap-x-24">
        <ul>
            <li class="list-heading">Menu</li>
            <li><a href="#about-us">Par mums</a></li>
            <li><a href="#coaches">Treneri</a></li>
            <li><a href="#services">Pakalpojumi</a></li>
            <li><a href="#contacts">Kontakti</a></li>
        </ul>
        <ul>
            <li class="list-heading">Informācija</li>
            <li><a href="#privacy-policy">Privātuma politika</a></li>
        </ul>
        <ul>
            <li class="list-heading">Kontakti</li>
            <li><a href="tel:+37126620757">+371 26620757</a></li>
            <li><a href="mailto:info@vingrosanasstudija.lv">info@vingrosanasstudija.lv</a></li>
            <li><a href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7">Strēlnieku iela 20 A<br />
                    Sigulda</a></li>
        </ul>
        <div class="md:w-full flex flex-col md:items-end">
            <ul class="list-heading">
                <li>Pieseko</li>
                <li class="md:flex items-end justify-end">
                    <a href="https://www.instagram.com/vingrosanas.studija" class="text-blue" target="_blank"
                        rel="noopener noreferrer">
                        <flux:icon.instagram />
                    </a>
                </li>
            </ul>
        </div>
    </div>
</footer>