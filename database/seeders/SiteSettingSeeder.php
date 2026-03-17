<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Seed the site settings with default homepage content.
     */
    public function run(): void
    {
        $settings = [
            'contact' => [
                ['key' => 'phone', 'value' => '+37126620757', 'type' => 'string'],
                ['key' => 'email', 'value' => 'info@vingrosanasstudija.lv', 'type' => 'string'],
                ['key' => 'address', 'value' => 'Strēlnieku iela 20 A, Sigulda', 'type' => 'string'],
                ['key' => 'google_maps_url', 'value' => 'https://maps.app.goo.gl/UdGP64Acxz2RVPJe7', 'type' => 'string'],
                ['key' => 'instagram_url', 'value' => 'https://www.instagram.com/vingrosanas.studija', 'type' => 'string'],
                ['key' => 'facebook_url', 'value' => 'https://www.facebook.com/vs.sigulda', 'type' => 'string'],
            ],
            'hero' => [
                ['key' => 'heading', 'value' => 'VINGROŠANAS STUDIJA veselīgam dzīvesveidam', 'type' => 'string'],
                ['key' => 'cta_text', 'value' => 'Pieteikties', 'type' => 'string'],
                ['key' => 'background_image', 'value' => 'images/header_image.jpg', 'type' => 'image'],
            ],
            'quote' => [
                ['key' => 'text', 'value' => '"Kustība ir dzīvesveids, veselība - ieguvums."', 'type' => 'text'],
                ['key' => 'attribution_name', 'value' => 'Anete Platkēviča', 'type' => 'string'],
                ['key' => 'attribution_title', 'value' => 'Vingrošanas studijas dibinātāja', 'type' => 'string'],
                ['key' => 'image_left', 'value' => 'images/anete_platkevica_7.jpg', 'type' => 'image'],
                ['key' => 'image_right', 'value' => 'images/anete_platkevica_8.jpg', 'type' => 'image'],
            ],
            'about' => [
                ['key' => 'about_content', 'value' => '<h3>Kas ir vingrošanas studija?</h3><p>"Vingrošanas studija" ir moderna studijas tipa sporta zāle, kas radīta dažāda vecuma cilvēkiem, kuri vēlas uzlabot veselību, atgūt kustību brīvību un stiprināt fizisko formu nepiespiestā un atbalstošā vidē.</p><p>Nodarbības ir piemērotas gan tiem, kuri tikai sāk sportot, gan tiem, kas atsāk fiziskās aktivitātes pēc ilgāka pārtraukuma, kā arī cilvēkiem, kuri vēlas uzlabot savu fizisko sagatavotību un kustību kvalitāti ikdienā.</p><h3>VINGROŠANAS STUDIJA piedāvā:</h3><ul><li>Grupu un individuālās nodarbības</li><li>Funkcionālo trenažieru zonu</li><li>Vingrošanu bērniem</li><li>Fizioterapeita un masāžas pakalpojumus</li></ul><h3>Mūsu pieeja:</h3><p>Darbā uzsvaru liekam uz kustību daudzveidību un brīvību, mobilitāti un vispārējo labsajūtu, palīdzot stiprināt veselību, samazināt traumu risku un radīt vairāk enerģijas ikdienas dzīvē.</p>', 'type' => 'html'],
                ['key' => 'goal_content', 'value' => '<p>Mūsu mērķis ir palīdzēt cilvēkiem stiprināt veselību, uzlabot kustību kvalitāti un mazināt traumu un slimību risku, veicinot aktīvu, apzinātu un sabalansētu dzīvesveidu. Mēs ticam, ka regulāra, pareizi vadīta kustība ir viens no nozīmīgākajiem faktoriem ilgtermiņa labsajūtas nodrošināšanā.</p><p>Mēs radām vidi, kurā ikviens – neatkarīgi no vecuma, pieredzes vai fiziskās sagatavotības līmeņa – jūtas droši, atbalstīti un motivēti. Mūsu mērķis nav tikai fizisks progress, bet arī pārliecības un pozitīvas attieksmes veidošana pret kustību kā ikdienas sastāvdaļu.</p>', 'type' => 'html'],
                ['key' => 'vision_content', 'value' => '<p>Mūsu vīzija ir kļūt par iedvesmojošu un inovatīvu vingrošanas studiju, kur kustība kļūst par dabisku dzīvesveida daļu. Mēs vēlamies būt vieta, kur cilvēki ne tikai uzlabo savu fizisko sagatavotību, bet arī attīsta izpratni par ķermeni, tā iespējām un nepieciešamībām.</p><p>Mēs tiecamies radīt vidi, kur profesionāla pieeja apvienojas ar cilvēcīgu attieksmi – vietu, kur klients jūtas sadzirdēts, novērtēts un iedrošināts. Ilgtermiņā mūsu mērķis ir veidot kopienu, kur veselība, kustība un labsajūta kļūst par vērtību, kas ietekmē dzīves kvalitāti arī ārpus studijas telpām.</p>', 'type' => 'html'],
                ['key' => 'mission_content', 'value' => '<p>Mūsu misija ir nodrošināt profesionālu, sistemātisku un individuāli pielāgotu pieeju katram klientam. Mēs strādājam rūpīgi un pētnieciskā veidā, izvērtējot katra cilvēka fizisko stāvokli, vajadzības un mērķus, lai nodrošinātu drošu un efektīvu progresu.</p><p>Mēs palīdzam stiprināt ķermeni, attīstīt stabilitāti, mobilitāti un kustību brīvību, vienlaikus mazinot traumu risku un veicinot vispārējo labsajūtu. Mūsu darbā svarīga ir uzticēšanās, sadarbība un ilgtermiņa rezultāts – nevis ātrs, bet ilgtspējīgs progress.</p>', 'type' => 'html'],
            ],
            'new_premises' => [
                ['key' => 'heading', 'value' => 'Jaunas telpas, jaunas iespējas', 'type' => 'string'],
                ['key' => 'description', 'value' => 'Mūsu vingrošanas studija tagad ir plašāka un jaudīgāka nekā jebkad - vieta, kur kustība kļūst par labsajūtu.', 'type' => 'text'],
                ['key' => 'bullet_points', 'value' => json_encode([
                    'Personalizēta treniņu programma vai treneru konsultācijas',
                    'Grupas nodarbības (joga, pilates, body balance, HIIT, u.c.)',
                    'Privātās treniņu sesijas',
                    'Vingrošana nelielās grupās',
                    'Pārģērbšanās un dušas telpas ar skapīšiem',
                    'Dzeramais ūdens',
                ], JSON_UNESCAPED_UNICODE), 'type' => 'json'],
                ['key' => 'images', 'value' => json_encode([
                    'images/anete_platkevica_1.jpg',
                    'images/anete_platkevica_2.jpg',
                    'images/anete_platkevica_3.jpg',
                ], JSON_UNESCAPED_UNICODE), 'type' => 'json'],
            ],
            'media' => [
                ['key' => 'video_thumbnail', 'value' => 'images/anete_platkevica_9.jpg', 'type' => 'image'],
                ['key' => 'video_file', 'value' => 'videos/rick-astley-never-gonna-give-you-up.mp4', 'type' => 'string'],
                ['key' => 'gallery_images', 'value' => json_encode([
                    'images/anete_platkevica_4.jpg',
                    'images/anete_platkevica_5.jpg',
                    'images/anete_platkevica_6.jpg',
                ], JSON_UNESCAPED_UNICODE), 'type' => 'json'],
            ],
            'seo' => [
                ['key' => 'meta_description', 'value' => 'Vingrošanas Studija Siguldā — grupu un individuālās nodarbības veselīgam dzīvesveidam. Pieteikties nodarbībām var tiešsaistē.', 'type' => 'text'],
                ['key' => 'og_image', 'value' => 'images/og-image.png', 'type' => 'image'],
            ],
        ];

        foreach ($settings as $group => $items) {
            foreach ($items as $item) {
                SiteSetting::updateOrCreate(
                    ['group' => $group, 'key' => $item['key']],
                    ['value' => $item['value'], 'type' => $item['type']],
                );
            }
        }
    }
}
