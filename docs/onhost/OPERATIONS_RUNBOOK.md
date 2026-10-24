# Provoz, incidenty a obnova

- **Local a CI:** jen izolované testovací údaje a fake providers.
- **Staging:** integrační ověření bez produkčního dopadu.
- **Production:** pouze po schválení, smluvní připravenosti, monitoringu a ověřené obnově.

## Bezpečný provozní zásah

1. Potvrdit prostředí, vlastníka a dopad.
2. Ověřit oprávnění, risk level, potřebný souhlas.
3. Ověřit zálohu **včetně skutečného restore testu**.
4. Provést dry-run a stanovit rollback/kompenzaci.
5. Spustit pouze schválenou změnu, ověřit stav na provideru.
6. Zaznamenat incident, evidence a komunikaci.

**Zvláštní projektové riziko:** AI popis není pravda bez zdroje; odstranit EXIF, platby nejsou produkční
