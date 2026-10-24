# Doménová data a invarianty

## Hlavní entity

- Property
- Listing
- Media
- Inquiry
- ModerationCase

## Model integrity

| Požadavek | Povinná vlastnost |
|---|---|
| Tenant isolation | Server-side ownership kontrola při každém dotazu |
| Historie | Záznam actor/action/time/result pro důležité změny |
| Idempotence | Duplicitní příkaz nezpůsobí duplicitní efekt |
| Retence | Účelově omezená podle právních a smluvních pravidel |
| Export | Zdokumentovaná oprávněná cesta ke strojově čitelným datům |
| Migrace | Test obnovy a pravidla rollback/kompenzace |

ERD a konkrétní typy zapisovat jen ze skutečných migrací nebo je označit `PROPOSED`.
