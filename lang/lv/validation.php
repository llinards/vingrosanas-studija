<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Lauks :attribute ir jāapstiprina.',
    'accepted_if' => 'Lauks :attribute ir jāapstiprina, kad :other ir :value.',
    'active_url' => 'Lauks :attribute ir jābūt derīgam URL.',
    'after' => 'Lauks :attribute ir jābūt datumam pēc :date.',
    'after_or_equal' => 'Lauks :attribute ir jābūt datumam pēc vai vienādam ar :date.',
    'alpha' => 'Lauks :attribute drīkst saturēt tikai burtus.',
    'alpha_dash' => 'Lauks :attribute drīkst saturēt tikai burtus, ciparus, domuzīmes un pasvītrojumus.',
    'alpha_num' => 'Lauks :attribute drīkst saturēt tikai burtus un ciparus.',
    'any_of' => 'Lauks :attribute ir nederīgs.',
    'array' => 'Lauks :attribute ir jābūt masīvam.',
    'ascii' => 'Lauks :attribute drīkst saturēt tikai viena baita burtciparu rakstzīmes un simbolus.',
    'before' => 'Lauks :attribute ir jābūt datumam pirms :date.',
    'before_or_equal' => 'Lauks :attribute ir jābūt datumam pirms vai vienādam ar :date.',
    'between' => [
        'array' => 'Laukam :attribute ir jābūt no :min līdz :max vienībām.',
        'file' => 'Laukam :attribute ir jābūt no :min līdz :max kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt no :min līdz :max.',
        'string' => 'Laukam :attribute ir jābūt no :min līdz :max rakstzīmēm.',
    ],
    'boolean' => 'Lauks :attribute ir jābūt patiesam vai nepatiesam.',
    'can' => 'Lauks :attribute satur neatļautu vērtību.',
    'confirmed' => 'Lauka :attribute apstiprinājums nesakrīt.',
    'contains' => 'Laukam :attribute trūkst obligātās vērtības.',
    'current_password' => 'Parole ir nepareiza.',
    'date' => 'Lauks :attribute ir jābūt derīgam datumam.',
    'date_equals' => 'Lauks :attribute ir jābūt datumam, kas vienāds ar :date.',
    'date_format' => 'Lauks :attribute ir jāatbilst formātam :format.',
    'decimal' => 'Laukam :attribute ir jābūt :decimal decimālzīmēm.',
    'declined' => 'Lauks :attribute ir jānoraida.',
    'declined_if' => 'Lauks :attribute ir jānoraida, kad :other ir :value.',
    'different' => 'Lauks :attribute un :other ir jābūt atšķirīgiem.',
    'digits' => 'Laukam :attribute ir jābūt :digits cipariem.',
    'digits_between' => 'Laukam :attribute ir jābūt no :min līdz :max cipariem.',
    'dimensions' => 'Laukam :attribute ir nederīgi attēla izmēri.',
    'distinct' => 'Laukam :attribute ir dublēta vērtība.',
    'doesnt_contain' => 'Lauks :attribute nedrīkst saturēt nevienu no šīm vērtībām: :values.',
    'doesnt_end_with' => 'Lauks :attribute nedrīkst beigties ar nevienu no šīm vērtībām: :values.',
    'doesnt_start_with' => 'Lauks :attribute nedrīkst sākties ar nevienu no šīm vērtībām: :values.',
    'email' => 'Lauks :attribute ir jābūt derīgai e-pasta adresei.',
    'encoding' => 'Lauks :attribute ir jābūt kodētam :encoding.',
    'ends_with' => 'Lauks :attribute ir jābeidzas ar vienu no šīm vērtībām: :values.',
    'enum' => 'Izvēlētais :attribute ir nederīgs.',
    'exists' => 'Izvēlētais :attribute ir nederīgs.',
    'extensions' => 'Laukam :attribute ir jābūt vienam no šiem paplašinājumiem: :values.',
    'file' => 'Lauks :attribute ir jābūt failam.',
    'filled' => 'Lauks :attribute ir jāaizpilda.',
    'gt' => [
        'array' => 'Laukam :attribute ir jābūt vairāk nekā :value vienībām.',
        'file' => 'Laukam :attribute ir jābūt lielākam par :value kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt lielākam par :value.',
        'string' => 'Laukam :attribute ir jābūt vairāk nekā :value rakstzīmēm.',
    ],
    'gte' => [
        'array' => 'Laukam :attribute ir jābūt :value vienībām vai vairāk.',
        'file' => 'Laukam :attribute ir jābūt lielākam vai vienādam ar :value kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt lielākam vai vienādam ar :value.',
        'string' => 'Laukam :attribute ir jābūt lielākam vai vienādam ar :value rakstzīmēm.',
    ],
    'hex_color' => 'Lauks :attribute ir jābūt derīgai heksadecimālai krāsai.',
    'image' => 'Lauks :attribute ir jābūt attēlam.',
    'in' => 'Izvēlētais :attribute ir nederīgs.',
    'in_array' => 'Lauks :attribute ir jābūt :other.',
    'in_array_keys' => 'Laukam :attribute ir jāsatur vismaz viena no šīm atslēgām: :values.',
    'integer' => 'Lauks :attribute ir jābūt veselam skaitlim.',
    'ip' => 'Lauks :attribute ir jābūt derīgai IP adresei.',
    'ipv4' => 'Lauks :attribute ir jābūt derīgai IPv4 adresei.',
    'ipv6' => 'Lauks :attribute ir jābūt derīgai IPv6 adresei.',
    'json' => 'Lauks :attribute ir jābūt derīgai JSON virknei.',
    'list' => 'Lauks :attribute ir jābūt sarakstam.',
    'lowercase' => 'Lauks :attribute ir jābūt ar mazajiem burtiem.',
    'lt' => [
        'array' => 'Laukam :attribute ir jābūt mazāk nekā :value vienībām.',
        'file' => 'Laukam :attribute ir jābūt mazākam par :value kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt mazākam par :value.',
        'string' => 'Laukam :attribute ir jābūt mazāk nekā :value rakstzīmēm.',
    ],
    'lte' => [
        'array' => 'Laukam :attribute nedrīkst būt vairāk nekā :value vienības.',
        'file' => 'Laukam :attribute ir jābūt mazākam vai vienādam ar :value kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt mazākam vai vienādam ar :value.',
        'string' => 'Laukam :attribute ir jābūt mazākam vai vienādam ar :value rakstzīmēm.',
    ],
    'mac_address' => 'Lauks :attribute ir jābūt derīgai MAC adresei.',
    'max' => [
        'array' => 'Laukam :attribute nedrīkst būt vairāk nekā :max vienības.',
        'file' => 'Laukam :attribute nedrīkst būt lielākam par :max kilobaitiem.',
        'numeric' => 'Laukam :attribute nedrīkst būt lielākam par :max.',
        'string' => 'Laukam :attribute nedrīkst būt vairāk nekā :max rakstzīmes.',
    ],
    'max_digits' => 'Laukam :attribute nedrīkst būt vairāk nekā :max cipari.',
    'mimes' => 'Lauks :attribute ir jābūt failam ar tipu: :values.',
    'mimetypes' => 'Lauks :attribute ir jābūt failam ar tipu: :values.',
    'min' => [
        'array' => 'Laukam :attribute ir jābūt vismaz :min vienībām.',
        'file' => 'Laukam :attribute ir jābūt vismaz :min kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt vismaz :min.',
        'string' => 'Laukam :attribute ir jābūt vismaz :min rakstzīmēm.',
    ],
    'min_digits' => 'Laukam :attribute ir jābūt vismaz :min cipariem.',
    'missing' => 'Lauks :attribute nedrīkst būt norādīts.',
    'missing_if' => 'Lauks :attribute nedrīkst būt norādīts, kad :other ir :value.',
    'missing_unless' => 'Lauks :attribute nedrīkst būt norādīts, ja vien :other nav :value.',
    'missing_with' => 'Lauks :attribute nedrīkst būt norādīts, kad ir norādīts :values.',
    'missing_with_all' => 'Lauks :attribute nedrīkst būt norādīts, kad ir norādīti :values.',
    'multiple_of' => 'Laukam :attribute ir jābūt :value daudzkārtnim.',
    'not_in' => 'Izvēlētais :attribute ir nederīgs.',
    'not_regex' => 'Lauka :attribute formāts ir nederīgs.',
    'numeric' => 'Lauks :attribute ir jābūt skaitlim.',
    'password' => [
        'letters' => 'Laukam :attribute ir jāsatur vismaz viens burts.',
        'mixed' => 'Laukam :attribute ir jāsatur vismaz viens lielais un viens mazais burts.',
        'numbers' => 'Laukam :attribute ir jāsatur vismaz viens cipars.',
        'symbols' => 'Laukam :attribute ir jāsatur vismaz viens simbols.',
        'uncompromised' => 'Norādītais :attribute ir parādījies datu noplūdē. Lūdzu, izvēlieties citu :attribute.',
    ],
    'present' => 'Lauks :attribute ir jābūt norādītam.',
    'present_if' => 'Lauks :attribute ir jābūt norādītam, kad :other ir :value.',
    'present_unless' => 'Lauks :attribute ir jābūt norādītam, ja vien :other nav :value.',
    'present_with' => 'Lauks :attribute ir jābūt norādītam, kad ir norādīts :values.',
    'present_with_all' => 'Lauks :attribute ir jābūt norādītam, kad ir norādīti :values.',
    'prohibited' => 'Lauks :attribute ir aizliegts.',
    'prohibited_if' => 'Lauks :attribute ir aizliegts, kad :other ir :value.',
    'prohibited_if_accepted' => 'Lauks :attribute ir aizliegts, kad :other ir apstiprināts.',
    'prohibited_if_declined' => 'Lauks :attribute ir aizliegts, kad :other ir noraidīts.',
    'prohibited_unless' => 'Lauks :attribute ir aizliegts, ja vien :other nav :values.',
    'prohibits' => 'Lauks :attribute aizliedz :other norādīšanu.',
    'regex' => 'Lauka :attribute formāts ir nederīgs.',
    'required' => 'Lauks :attribute ir obligāts.',
    'required_array_keys' => 'Laukam :attribute ir jāsatur ieraksti šādiem: :values.',
    'required_if' => 'Lauks :attribute ir obligāts, kad :other ir :value.',
    'required_if_accepted' => 'Lauks :attribute ir obligāts, kad :other ir apstiprināts.',
    'required_if_declined' => 'Lauks :attribute ir obligāts, kad :other ir noraidīts.',
    'required_unless' => 'Lauks :attribute ir obligāts, ja vien :other nav :values.',
    'required_with' => 'Lauks :attribute ir obligāts, kad ir norādīts :values.',
    'required_with_all' => 'Lauks :attribute ir obligāts, kad ir norādīti :values.',
    'required_without' => 'Lauks :attribute ir obligāts, kad :values nav norādīts.',
    'required_without_all' => 'Lauks :attribute ir obligāts, kad neviens no :values nav norādīts.',
    'same' => 'Lauks :attribute ir jāsakrīt ar :other.',
    'size' => [
        'array' => 'Laukam :attribute ir jāsatur :size vienības.',
        'file' => 'Laukam :attribute ir jābūt :size kilobaitiem.',
        'numeric' => 'Laukam :attribute ir jābūt :size.',
        'string' => 'Laukam :attribute ir jābūt :size rakstzīmēm.',
    ],
    'starts_with' => 'Lauks :attribute ir jāsākas ar vienu no šīm vērtībām: :values.',
    'string' => 'Lauks :attribute ir jābūt virknei.',
    'timezone' => 'Lauks :attribute ir jābūt derīgai laika joslai.',
    'unique' => ':attribute jau ir aizņemts.',
    'uploaded' => 'Neizdevās augšupielādēt :attribute.',
    'uppercase' => 'Lauks :attribute ir jābūt ar lielajiem burtiem.',
    'url' => 'Lauks :attribute ir jābūt derīgam URL.',
    'ulid' => 'Lauks :attribute ir jābūt derīgam ULID.',
    'uuid' => 'Lauks :attribute ir jābūt derīgam UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
