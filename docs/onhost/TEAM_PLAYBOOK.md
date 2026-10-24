# Týmové workflow

**Produktový vlastník:** Adrian Staněk. Konkrétní tech lead a reviewer jsou určováni u jednotlivých PR; neodhadovat jejich osobní identity.

```mermaid
flowchart LR
 Issue[Issue a AC] --> Scope[Scope a riziko]
 Scope --> Branch[Izolovaná branch/worktree]
 Branch --> Test[Testy a bezpečnost]
 Test --> PR[Draft PR]
 PR --> Review[Code review]
 Review --> Staging[Staging verification]
 Staging --> Release[Schválený release]
```

## Pravidla komunikace

- Handoff obsahuje commit/branch, soubory, test commands + skutečné exit codes, blokery a další krok.
- PR popisuje zákaznickou hodnotu, schema změny, rizika a plán obnovy.
- Schválení identity, financí, zdravotních dat nebo nevratných operací vyžaduje určeného člověka.
- Elaris může tvořit návrhy, testy a PR. Nemůže si sám rozšířit oprávnění.
