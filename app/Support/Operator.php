<?php

namespace App\Support;

/**
 * Jediný zdroj údajů o provozovateli projektu (kontrakt onhost-operator/1, config/operator.php).
 * Neznámý slug v ECOSYSTEM_OPERATOR spadne na výchozí subjekt, aby web nikdy neukázal prázdného prodávajícího.
 */
final class Operator
{
    public static function slug(): string
    {
        $slug = (string) config('operator.operator', 'adrian-stanek');

        return is_array(config("operator.operators.$slug")) ? $slug : (string) config('operator.default', 'adrian-stanek');
    }

    /** Úplné údaje aktivního provozovatele (+ slug). @return array<string, mixed> */
    public static function current(): array
    {
        $slug = self::slug();

        return ['slug' => $slug] + (array) config("operator.operators.$slug", []);
    }

    public static function get(string $field, mixed $default = null): mixed
    {
        return data_get(self::current(), $field, $default);
    }

    public static function vatPayer(): bool
    {
        return (bool) self::get('vat_payer', false);
    }

    /** Veřejná část pro katalog ONhost ID (pole `seller`). @return array{name: string, ico: string, dic: string, vat_payer: bool, legal_form: string} */
    public static function seller(): array
    {
        $op = self::current();

        return [
            'name' => (string) ($op['name'] ?? ''),
            'ico' => (string) ($op['ico'] ?? ''),
            'dic' => (string) ($op['dic'] ?? ''),
            'vat_payer' => (bool) ($op['vat_payer'] ?? false),
            'legal_form' => (string) ($op['legal_form'] ?? 'other'),
        ];
    }

    /** Kopie údajů pro doklad v okamžiku vystavení (bez kontaktů, které se mohou měnit nezávisle). @return array<string, mixed> */
    public static function snapshot(): array
    {
        $op = self::current();

        return [
            'slug' => $op['slug'],
            'name' => (string) ($op['name'] ?? ''),
            'legal_form' => (string) ($op['legal_form'] ?? ''),
            'ico' => (string) ($op['ico'] ?? ''),
            'dic' => (string) ($op['dic'] ?? ''),
            'vat_payer' => (bool) ($op['vat_payer'] ?? false),
            'vat_id' => (string) ($op['vat_id'] ?? ''),
            'address' => (array) ($op['address'] ?? []),
            'registry' => (string) ($op['registry'] ?? ''),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /** Text pro patičku, např. „Provozovatel: Adrian Staněk, IČO 08094616, neplátce DPH“. */
    public static function footer(): string
    {
        return (string) self::get('footer', '');
    }
}
