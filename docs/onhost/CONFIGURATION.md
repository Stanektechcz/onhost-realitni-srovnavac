# Konfigurace a secrets

## Projektové předpoklady

AI popis není pravda bez zdroje; odstranit EXIF, platby nejsou produkční

## Standard

- Dev, CI, staging a produkce používají oddělené klíče, databáze a provider účty.
- Tajemství mimo Git/README/prompty; secret manager a rotace přístupů.
- Feature flags mají výchozí hodnotu, popis závislostí, bezpečný fallback a test.
- Výpadek externí služby nesmí vytvořit tichý souhlas ani prohlásit akci za dokončenou.
- Zakázané: produkční migrace při lokálním testu, logování tokenů nebo osobních údajů.
