<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Jediný zdroj údajů o provozovateli projektu (kontrakt onhost-operator/1, config/operator.php).
 * Neznámý slug v ECOSYSTEM_OPERATOR spadne na výchozí subjekt, aby web nikdy neukázal prázdného prodávajícího.
 */
final class Operator
{
    /** Klíč aktivního subjektu v registru; ECOSYSTEM_OPERATOR smí být slug (alias) i id kterékoli verze. */
    public static function slug(): string
    {
        $want = (string) config('operator.operator', '');
        foreach ((array) config('operator.operators', []) as $slug => $entry) {
            if ($want === (string) $slug) {
                return (string) $slug;
            }
            foreach (self::versions((string) $slug) as $op) {
                if ($want === (string) ($op['id'] ?? '')) {
                    return (string) $slug;
                }
            }
        }

        return (string) config('operator.default', 'adrian-stanek');
    }

    /**
     * Všechny verze subjektu (validFrom historie): záznam registru je buď jedna verze, nebo seznam verzí.
     *
     * @return list<array<string, mixed>>
     */
    public static function versions(string $slug): array
    {
        $entry = (array) config("operator.operators.$slug", []);
        if ($entry === []) {
            return [];
        }

        return array_is_list($entry) ? array_values(array_filter($entry, 'is_array')) : [$entry];
    }

    /**
     * Verze subjektu platná v okamžiku $at: nejvyšší valid_from <= $at (prázdné valid_from = odjakživa). Když ještě žádná
     * neplatí, vrátí nejstarší, aby web nikdy neukázal prázdného prodávajícího. @return array<string, mixed>
     */
    public static function resolve(string $slug, ?\DateTimeInterface $at = null): array
    {
        $at = Carbon::instance($at ?? now());
        $best = null;
        $bestFrom = null;
        $earliest = null;
        $earliestFrom = null;
        foreach (self::versions($slug) as $op) {
            $raw = trim((string) ($op['valid_from'] ?? ''));
            $from = $raw === '' ? Carbon::createFromTimestamp(0) : Carbon::parse($raw);
            if ($earliestFrom === null || $from->lt($earliestFrom)) {
                [$earliest, $earliestFrom] = [$op, $from];
            }
            if ($from->lte($at) && ($bestFrom === null || $from->gte($bestFrom))) {
                [$best, $bestFrom] = [$op, $from];
            }
        }

        return (array) ($best ?? $earliest ?? []);
    }

    /** Identifikátor subjektu (např. osvc-stanek-<IČO>), ukládá se do snapshotů. */
    public static function id(): string
    {
        return (string) (self::get('id') ?: self::slug());
    }

    /** Úplné údaje aktivního provozovatele (+ slug): aktivní verze podle valid_from. @return array<string, mixed> */
    public static function current(): array
    {
        $slug = self::slug();

        return ['slug' => $slug] + self::resolve($slug);
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
            'id' => (string) ($op['id'] ?? $op['slug']),
            'slug' => $op['slug'],
            'name' => (string) ($op['name'] ?? ''),
            'legal_form' => (string) ($op['legal_form'] ?? ''),
            'ico' => (string) ($op['ico'] ?? ''),
            'dic' => (string) ($op['dic'] ?? ''),
            'vat_mode' => (string) ($op['vat_mode'] ?? 'non_payer'),
            'vat_payer' => (bool) ($op['vat_payer'] ?? false),
            'vat_id' => (string) ($op['vat_id'] ?? ''),
            'address' => (array) ($op['address'] ?? []),
            'show_address_public' => LegalEntity::showAddressPublic(),
            'registry' => (string) ($op['registry'] ?? ''),
            'registry_note' => (string) ($op['registry_note'] ?? ''),
            'privacy_url' => (string) ($op['privacy_url'] ?? ''),
            'valid_from' => (string) ($op['valid_from'] ?? ''),
            'number_prefix' => DocumentNumber::prefix($op['slug']),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /** Text pro patičku, např. „Provozovatel: <jméno>, IČO <IČO>, neplátce DPH“. */
    public static function footer(): string
    {
        return (string) self::get('footer', '');
    }
}
