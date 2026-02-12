@props([
'section',
])

@switch($section)
@case('about')
<flux:heading level="3">{{ __('Kas ir vingrošanas studija?') }}</flux:heading>
<flux:text>
    {{ __('“Vingrošanas studija” ir moderna sporta zāle
    studijas formātā, kas radīta dažāda vecuma
    cilvēkiem, kuri vēlas uzlabot veselību, atgūt
    kustību brīvību un stiprināt sportisko formu
    nepiespiestā un atbalstošā vidē.') }}
</flux:text>

<flux:heading level="3">{{ __('Ko vingrošanas studija piedāvā') }}</flux:heading>
<ul class="list-disc pl-4">
    <li>{{ __('Grupas un individuālās nodarbības') }}</li>
    <li>{{ __('Funkcionālo trenažieru zonu') }}</li>
    <li>{{ __('Fizioterapeita un masāžas pakalpojumus') }}</li>
</ul>

<flux:text>
    {{ __('Mūsu nodarbības ir piemērotas ikvienam – gan
    iesācējiem vai tiem, kas atsāk pēc pārtraukuma,
    gan tiem, kas vēlas paaugstināt fizisko
    sagatavotību un kustību kvalitāti ikdienā.') }}
</flux:text>

<flux:heading level="3">{{ __('Mūsu pieeja') }}</flux:heading>
<flux:text>
    {{ __('Uzsvaru liekam uz kustību brīvību, stabilitāti,
    mobilitāti un vispārējo labsajūtu. Mēs palīdzam
    stiprināt veselību, samazināt traumu risku un
    radīt vairāk enerģijas ikdienas dzīvei.') }}
</flux:text>
@break

@case('goal')
<flux:text>
    Lorem ipsum dolor sit amet consectetur adipisicing elit. Doloremque non id nam illo incidunt,
    quas accusamus asperiores dignissimos corrupti nobis nulla aperiam maiores voluptatem atque
    quasi unde eaque, modi reiciendis!
    Iste at tempora repudiandae praesentium obcaecati libero. Excepturi architecto delectus saepe
    soluta culpa nesciunt consequatur sunt? Ipsum neque quasi saepe, veniam distinctio consequatur,
    quas iure eum praesentium alias, exercitationem numquam!
</flux:text>
@break

@case('vision')
<flux:text>
    Lorem ipsum dolor sit amet consectetur adipisicing elit. Doloremque non id nam illo incidunt,
    quas accusamus asperiores dignissimos corrupti nobis nulla aperiam maiores voluptatem atque
    quasi unde eaque, modi reiciendis!
    Iste at tempora repudiandae praesentium obcaecati libero. Excepturi architecto delectus saepe
    soluta culpa nesciunt consequatur sunt? Ipsum neque quasi saepe, veniam distinctio consequatur,
    quas iure eum praesentium alias, exercitationem numquam!
</flux:text>
@break

@case('mission')
<flux:text>
    Lorem ipsum dolor sit amet consectetur adipisicing elit. Doloremque non id nam illo incidunt,
    quas accusamus asperiores dignissimos corrupti nobis nulla aperiam maiores voluptatem atque
    quasi unde eaque, modi reiciendis!
    Iste at tempora repudiandae praesentium obcaecati libero. Excepturi architecto delectus saepe
    soluta culpa nesciunt consequatur sunt? Ipsum neque quasi saepe, veniam distinctio consequatur,
    quas iure eum praesentium alias, exercitationem numquam!
</flux:text>
@break
@endswitch