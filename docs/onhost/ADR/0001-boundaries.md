# ADR-0001 — Hranice a vlastníci produktu

- **Stav:** proposed (schválit vlastníkem)
- **Produkt:** ONHOST Reality
- **Datum:** 2026-10-24

## Kontext

Český portál inzerce nemovitostí, vyhledávání, mapa, alerty, moderace a pozdější AI draft přes Elaris Mate

## Navržené rozhodnutí

Produkt vlastní své doménové entity. Identitu sdílí pouze přes ONHOST ID, hostingové a finanční operace přes ONHOST Core. Elaris zůstává autorizovaným klientem oficiálních API.

## Tradeoffs

Plus: testovatelnost, izolace, audit, predikovatelnost. Minus: nároky na kontraktní testy, kompatibilitu a retry.

## Otevřené otázky

Jaké jsou právní povinnosti, jaké externí smlouvy, kdo provádí risk approval a jaký bude postup migrace? Status změnit na accepted pouze po schválení.
