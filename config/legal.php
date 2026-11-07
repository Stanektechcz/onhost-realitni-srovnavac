<?php

// Provozovatel portálu (prodávající / správce údajů). Dnes OSVČ, později může být dceřiná s.r.o.
// nebo jedna firma pro celý ekosystém – mění se jen proměnnými prostředí, nikdy v kódu.
return [
    'name' => env('LEGAL_NAME', 'Adrian Staněk'),
    'form' => env('LEGAL_FORM', 'osvc'), // osvc | sro | as
    'ico' => env('LEGAL_ICO', '08094616'),
    'dic' => env('LEGAL_DIC', ''),
    'vat_payer' => (bool) env('LEGAL_VAT_PAYER', false),
    'address' => env('LEGAL_ADDRESS', ''),
    'email' => env('LEGAL_EMAIL', ''),
    'register' => env('LEGAL_REGISTER', ''), // zápis v OR, u OSVČ prázdné
    'iban' => env('LEGAL_IBAN', ''),
];
