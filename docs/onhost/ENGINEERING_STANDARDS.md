# Inženýrské standardy ONHOST

- Změna musí mít issue, akceptační scénář, majitele a předpoklady.
- Zdroj pravdy: skutečný commit, manifest, route, kontrakt, migrace a testy.
- Jeden PR řeší vymezený problém. U větších změn použít ADR.
- Oprávnění a tenant izolaci validovat vždy na serveru.
- Unit, integration, negativní test, review a CI evidence jsou součástí DoD.
- Nezapisovat do cizích databází, neobcházet Elaris/ONHOST bezpečnostní brány.
- Nevyužívat zákaznická data ani produkční tajemství pro vývoj či AI trénování.
- Každá změna aktualizuje odpovídající docs, API a provozní runbook.

**Projektové riziko:** AI popis není pravda bez zdroje; odstranit EXIF, platby nejsou produkční
