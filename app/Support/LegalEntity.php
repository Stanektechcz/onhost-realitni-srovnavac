<?php

namespace App\Support;

/** Údaje o provozovateli z config/legal.php – jediný zdroj pravdy pro patičku, právní texty i platby. */
class LegalEntity
{
    public const FORMS = ['osvc' => 'OSVČ', 'sro' => 's.r.o.', 'as' => 'a.s.'];

    public static function get(string $key): mixed
    {
        return config('legal.'.$key);
    }

    public static function name(): string
    {
        return (string) config('legal.name');
    }

    public static function ico(): string
    {
        return (string) config('legal.ico');
    }

    public static function formLabel(): string
    {
        return self::FORMS[config('legal.form')] ?? (string) config('legal.form');
    }

    public static function formDescription(): string
    {
        return config('legal.form') === 'osvc' ? 'fyzická osoba podnikající' : 'právnická osoba';
    }

    public static function isVatPayer(): bool
    {
        return (bool) config('legal.vat_payer');
    }

    public static function vatStatus(): string
    {
        return self::isVatPayer() ? 'plátce DPH'.(config('legal.dic') ? ', DIČ '.config('legal.dic') : '') : 'neplátce DPH';
    }

    /** Např. „Jméno, OSVČ, IČO …, neplátce DPH“. */
    public static function displayLine(): string
    {
        return self::name().', '.self::formLabel().', IČO '.self::ico().', '.self::vatStatus();
    }

    /** Štítek k cenám. */
    public static function priceNote(): string
    {
        return self::isVatPayer() ? 'vč. DPH' : 'nejsme plátci DPH';
    }
}
