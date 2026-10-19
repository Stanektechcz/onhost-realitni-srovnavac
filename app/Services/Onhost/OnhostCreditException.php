<?php

namespace App\Services\Onhost;

use RuntimeException;

/** A refused or failed ONhost credit / ONhost ID call. `error` is ONhost's machine slug; the message is for the user (Czech). */
class OnhostCreditException extends RuntimeException
{
    private const MESSAGES = [
        'insufficient_funds' => 'Na kreditu ONhost nemáte dost prostředků. Dobijte si kredit v ONhost ID nebo zvolte jinou platbu.',
        'consent_required' => 'Platba je vyšší než limit pro platbu kreditem bez dalšího potvrzení. Zvolte prosím jinou platbu.',
        'idempotency_conflict' => 'Tato platba už byla v ONhost zpracována s jinými údaji. Kontaktujte prosím podporu.',
        'ecosystem_daily_limit' => 'Dnešní limit plateb kreditem ONhost je vyčerpán. Zkuste to zítra nebo zvolte jinou platbu.',
        'ecosystem_rate_limited' => 'Příliš mnoho požadavků na kredit ONhost. Zkuste to prosím za chvíli.',
        'invalid_client' => 'Platba kreditem ONhost je dočasně nedostupná (chyba nastavení aplikace).',
        'ecosystem_app_unknown' => 'Platba kreditem ONhost je dočasně nedostupná (aplikace není v ONhost registrována).',
        'ecosystem_client_not_confidential' => 'Platba kreditem ONhost je dočasně nedostupná (chyba nastavení aplikace).',
        'ecosystem_family_not_allowed' => 'Tuto službu nelze zaplatit kreditem ONhost.',
        'wallet_consent_missing' => 'Nepovolili jste platby kreditem ONhost. Propojte účet s ONhost ID znovu a platbu povolte.',
        'wallet_organization_not_consented' => 'Platbu nelze provést z této organizace. Propojte účet s ONhost ID znovu.',
        'insufficient_scope' => 'Propojení s ONhost ID neumožňuje platby. Propojte účet znovu a platby kreditem povolte.',
        'invalid_token' => 'Přihlášení k ONhost ID vypršelo. Propojte účet s ONhost ID znovu.',
        'forbidden' => 'Nemáte oprávnění platit kreditem této organizace.',
        'wallet_frozen' => 'Váš kredit ONhost je zablokovaný. Kontaktujte podporu ONhost.',
        'ecosystem_refund_exceeds_charge' => 'Vrácení je vyšší než zbývající částka platby.',
        'ecosystem_charge_refunded' => 'Platba už byla vrácena.',
        'not_connected' => 'Nejprve propojte svůj účet s ONhost ID.',
        'unavailable' => 'Služba ONhost je teď nedostupná. Zkuste to prosím později.',
    ];

    public function __construct(public readonly string $error, public readonly int $status = 0)
    {
        parent::__construct($error);
    }

    public function userMessage(): string
    {
        return self::MESSAGES[$this->error] ?? 'Platba kreditem ONhost se nezdařila. Zkuste to prosím později nebo zvolte jinou platbu.';
    }

    /** The user must (re)connect ONhost ID to continue. */
    public function needsReconnect(): bool
    {
        return in_array($this->error, ['not_connected', 'invalid_token', 'wallet_consent_missing', 'insufficient_scope', 'wallet_organization_not_consented'], true);
    }
}
