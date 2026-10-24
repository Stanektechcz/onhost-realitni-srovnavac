# ONHOST integrace a jejich odpovědnost

ONHOST ID/Wallet za feature flags, Elaris Mate

| Služba | Odpovídá za | Zákaz |
|---|---|---|
| ONHOST ID | OIDC identita, consent a scopes | Není univerzálním oprávněním číst data |
| ONHOST Core | Billing, wallet, hosting a provisioning | Nelze obcházet CommandBus či double-entry |
| Elaris Mate | Intent → plán → approval → API | Nesmí provádět shell/SQL bypass |
| Externí provider | Specifický technický úkon | Nepovažovat callback bez podpisu za důvěryhodný |

## Přidání konektoru

Kontrakt → fake/sandbox → auth scopes → retry/idempotence → negativní test → staging evidence → risk review → řízené zapnutí.
