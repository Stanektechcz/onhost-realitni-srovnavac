<?php

namespace App\Support;

/**
 * Prezentační pohled na provozovatele pro patičku, právní texty i platby. Údaje se berou jen z registru
 * provozovatelů (App\Support\Operator, config/operator.php, kontrakt onhost-operator/1).
 */
class LegalEntity
{
    public const FORMS = ['osvc' => 'OSVČ', 'sro' => 's.r.o.', 'as' => 'a.s.', 'zs' => 'z.s.'];

    public static function get(string $key): mixed
    {
        return Operator::get($key);
    }

    public static function name(): string
    {
        return (string) Operator::get('name', '');
    }

    public static function ico(): string
    {
        return (string) Operator::get('ico', '');
    }

    public static function form(): string
    {
        return (string) Operator::get('legal_form', '');
    }

    public static function formLabel(): string
    {
        return self::FORMS[self::form()] ?? self::form();
    }

    public static function formDescription(): string
    {
        return self::form() === 'osvc' ? 'fyzická osoba podnikající' : 'právnická osoba';
    }

    public static function isVatPayer(): bool
    {
        return Operator::vatPayer();
    }

    public static function dic(): string
    {
        return (string) Operator::get('dic', '');
    }

    /** Zápis v obchodním rejstříku; u OSVČ prázdné (živnostenský rejstřík se v kontaktech neuvádí). */
    public static function register(): string
    {
        return self::form() === 'osvc' ? '' : (string) Operator::get('registry', '');
    }

    public static function vatStatus(): string
    {
        return self::isVatPayer() ? 'plátce DPH'.(self::dic() !== '' ? ', DIČ '.self::dic() : '') : 'neplátce DPH';
    }

    /** Např. „Jméno, OSVČ, IČO …, neplátce DPH“ (forma se neopakuje, když je už v názvu). */
    public static function displayLine(): string
    {
        $form = self::formLabel();
        $name = self::name();
        $parts = [$name];
        if ($form !== '' && ! str_ends_with($name, $form)) {
            $parts[] = $form;
        }

        return implode(', ', $parts).', IČO '.self::ico().', '.self::vatStatus();
    }

    /** Smí veřejný web ukázat ulici sídla? Pole show_address_public; chybí-li, OSVČ ne (sídlo = bydliště), firmy ano. */
    public static function showAddressPublic(): bool
    {
        $v = Operator::get('show_address_public');
        if ($v === null || $v === '') {
            return self::form() !== 'osvc';
        }

        return is_bool($v) ? $v : filter_var($v, FILTER_VALIDATE_BOOL);
    }

    /** Plná adresa sídla („ulice, PSČ obec“), jak ji mají doklady; '' když chybí. */
    public static function address(): string
    {
        $a = (array) Operator::get('address', []);
        $city = trim(trim((string) ($a['zip'] ?? '')).' '.trim((string) ($a['city'] ?? '')));

        return implode(', ', array_filter([trim((string) ($a['street'] ?? '')), $city]));
    }

    /** Sídlo pro veřejné stránky (kontakt, o nás, JSON-LD): plná adresa, nebo '' když se nezveřejňuje. */
    public static function publicAddress(): string
    {
        return self::showAddressPublic() ? self::address() : '';
    }

    /** Štítek k cenám. */
    public static function priceNote(): string
    {
        return self::isVatPayer() ? 'vč. DPH' : 'nejsme plátci DPH';
    }
}
