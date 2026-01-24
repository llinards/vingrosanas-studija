<footer>
    <div class="container mx-auto px-4">
        <div
            class="flex flex-col space-y-2 md:space-y-0 md:flex-row text-center md:text-right items-center justify-between border-y border-black py-3">
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
                <li>
                    <flux:link href="#about-us">Par mums</flux:link>
                </li>
                <li>
                    <flux:link href="#coaches">Treneri</flux:link>
                </li>
                <li>
                    <flux:link href="#services">Pakalpojumi</flux:link>
                </li>
                <li>
                    <flux:link href="#contacts">Kontakti</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">Informācija</li>
                <li>
                    <flux:link href="#privacy-policy">Privātuma politika</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">Kontakti</li>
                <li>
                    <flux:link href="tel:+37126620757">+371 26620757</flux:link>
                </li>
                <li>
                    <flux:link href="mailto:info@vingrosanasstudija.lv">info@vingrosanasstudija.lv</flux:link>
                </li>
                <li>
                    <flux:link href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7">Strēlnieku iela 20 A<br />
                        Sigulda</flux:link>
                </li>
            </ul>
            <div class="md:w-full flex flex-col md:items-end">
                <ul class="list-heading">
                    <li>Pieseko</li>
                    <li class="md:flex items-end justify-end mt-1 md:mt-2">
                        <flux:link href="https://www.instagram.com/vingrosanas.studija" class="text-blue"
                            target="_blank" rel="noopener noreferrer">
                            <flux:icon.instagram />
                        </flux:link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="bg-black py-6">
        <div class="container mx-auto space-y-2 text-center">
            <flux:text>© VINGROŠANAS STUDIJA 2026 | Visas tiesības rezervētas.</flux:text>
            <flux:text>Mājaslapas dizainu izstrādāja <flux:link href="https://www.simpledesign.lv" target="_black"
                    noopener noreferrer>
                    SIMPLE DESIGN</flux:link>, izstrādi
                veica <flux:link href="https://www.slmedia.lv" target="_black" noopener noreferrer>S&L
                    Media</flux:link>
                .</flux:text>
        </div>
    </div>
</footer>