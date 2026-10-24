# Security, privacy a threat model

**Specifické riziko:** AI popis není pravda bez zdroje; odstranit EXIF, platby nejsou produkční

| Hrozba | Požadovaná kontrola |
|---|---|
| IDOR a tenant escape | server-side policy a negativní test |
| Replay webhooku | signature, timestamp, deduplikace |
| Secret leakage | vault, redakce logů, rotace |
| Prompt injection | obsah z externích systémů není instrukce |
| Destruktivní zápis | preview, step-up, approval a audit |
| Incident | izolovat, obnovit bezpečně, postmortem |

Nezveřejňovat zranitelnosti veřejným issue. Citlivé údaje používat jen na schváleném právním základě a s příslušnou retencí.
