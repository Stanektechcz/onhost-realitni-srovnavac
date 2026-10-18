#!/usr/bin/env python3
"""ONhost Reality Research - bezpecny adapter pro sber realitnich nabidek.

Vlastni implementace. Inspirovano myslenkou (seznam portalu, typ pristupu) projektu
janchaloupka/web-scraper-nabidek-pronajmu; z nej NENI prevzat zadny kod
(repozitar nema licenci = vsechna prava vyhrazena).

Vystup: JSONL bez osobnich udaju (jmena, telefony, e-maily) a bez fotek.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import time
import urllib.robotparser
from dataclasses import dataclass, asdict, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.parse import urlparse

try:
    import requests
except ImportError:  # pragma: no cover
    requests = None

USER_AGENT = "onhost-research/0.1 (+https://reality.onhost.cz/bot; mailto:info@onhost.cz)"
MIN_INTERVAL = 10.0          # 1 pozadavek / 10 s / domena (nelze snizit)
MAX_PER_DAY = 500            # max pozadavku / den / portal
ALLOWED = {"bezrealitky", "idnes", "eurobydleni"}  # vysledek 05-kontrola-podminek-portalu.md
FORBIDDEN = {"sreality", "realcity", "ulovdomov", "remax", "realingo", "bravis", "bazos"}
CAPTCHA_RE = re.compile(r"captcha|cf-challenge|challenge-platform|just a moment|attention required", re.I)
HERE = Path(__file__).resolve().parent


# ---------------------------------------------------------------- model
@dataclass
class Listing:
    """Jen pole pro agregat (rozhodnuti 2026-10-18). Zadne URL, texty, fotky ani kontakty."""
    source: str
    source_id: str                  # SHA-256 hash puvodniho ID (ne puvodni ID)
    offer_type: str                 # "rent" | "sale"
    property_type: str = "flat"
    disposition: str | None = None
    area_m2: float | None = None
    price_czk: int | None = None
    city: str | None = None
    district: str | None = None
    lat: float | None = None        # zaokrouhleno na 3 des. mista
    lng: float | None = None
    penb: str | None = None
    published_at: str | None = None


def make_listing(source: str, raw_id, **kw) -> Listing:
    for k in ("lat", "lng"):
        if kw.get(k) is not None:
            kw[k] = round(float(kw[k]), 3)
    for k in ("city", "district"):
        kw[k] = scrub(kw.get(k))
    sid = hashlib.sha256(f"{source}:{raw_id}".encode()).hexdigest()[:32]
    return Listing(source=source, source_id=sid, **kw)


PII_RE = re.compile(r"[\w.+-]+@[\w-]+\.[\w.]+|(\+?420)?[\s-]?\d{3}[\s-]?\d{3}[\s-]?\d{3}")


def scrub(text: str | None) -> str | None:
    """Odstrani pripadne e-maily/telefony z volneho textu (lokalita)."""
    return PII_RE.sub("[odstraneno]", text).strip() if text else text


# ---------------------------------------------------------------- HTTP vrstva
class PoliteClient:
    """Kontrola robots.txt + rate-limit na domenu + identifikovatelny UA."""

    def __init__(self, min_interval: float = MIN_INTERVAL, dry_run: bool = False, budget: int = 300):
        self.min_interval = max(MIN_INTERVAL, float(min_interval))
        self.budget = budget          # celkovy strop pozadavku napric portaly
        self.count: dict[str, int] = {}
        self.stopped: set[str] = set()
        self.dry_run = dry_run
        self._last: dict[str, float] = {}
        self._robots: dict[str, urllib.robotparser.RobotFileParser] = {}
        self.session = requests.Session() if requests else None
        if self.session:
            self.session.headers.update({"User-Agent": USER_AGENT, "Accept-Language": "cs"})

    def _wait(self, host: str) -> None:
        delta = time.monotonic() - self._last.get(host, 0.0)
        if delta < self.min_interval:
            time.sleep(self.min_interval - delta)
        self._last[host] = time.monotonic()

    def robots(self, url: str) -> urllib.robotparser.RobotFileParser:
        p = urlparse(url)
        base = f"{p.scheme}://{p.netloc}"
        if base not in self._robots:
            rp = urllib.robotparser.RobotFileParser(base + "/robots.txt")
            self._wait(p.netloc)
            try:
                if self.session:
                    r = self.session.get(base + "/robots.txt", timeout=20)
                    if r.status_code in (401, 403):
                        rp.disallow_all = True
                    elif r.status_code >= 400:
                        rp.allow_all = True  # dle RFC 9309: 4xx = bez omezeni
                    else:
                        rp.parse(r.text.splitlines())
                else:
                    rp.read()
            except Exception:
                rp.disallow_all = True  # nejistota => nestahovat
            self._robots[base] = rp
        return self._robots[base]

    def allowed(self, url: str) -> bool:
        return self.robots(url).can_fetch(USER_AGENT, url)

    def request(self, method: str, url: str, **kw):
        if not self.allowed(url):
            raise PermissionError(f"robots.txt zakazuje: {url}")
        if self.dry_run:
            print(f"[dry-run] {method} {url}", file=sys.stderr)
            return None
        rp = self.robots(url)
        delay = rp.crawl_delay(USER_AGENT)
        if delay:
            self.min_interval = max(self.min_interval, float(delay))
        host = urlparse(url).netloc
        if host in self.stopped:
            raise RuntimeError(f"{host} je zastaven (drive 403/429/captcha)")
        if self.budget <= 0 or self.count.get(host, 0) >= MAX_PER_DAY:
            raise RuntimeError("vycerpan limit pozadavku")
        self._wait(host)
        self.budget -= 1
        self.count[host] = self.count.get(host, 0) + 1
        r = self.session.request(method, url, timeout=30, allow_redirects=False, **kw)
        if r.status_code in (401, 403, 429) or CAPTCHA_RE.search(r.text[:20000] or ""):
            self.stopped.add(host)
            raise RuntimeError(f"{r.status_code}/anti-bot od {host} - STOP, nic neobchazim")
        r.raise_for_status()
        return r


# ---------------------------------------------------------------- adaptery
class Adapter:
    name = ""
    def endpoints(self, offer_type: str) -> list[str]: raise NotImplementedError
    def fetch(self, client: PoliteClient, offer_type: str, max_pages: int): raise NotImplementedError


def _num(v):
    try:
        return float(v) if v is not None else None
    except (TypeError, ValueError):
        return None


def _disp(text: str | None) -> str | None:
    m = re.search(r"(\d)\s*\+\s*(kk|\d)", text or "", re.I)
    return f"{m.group(1)}+{m.group(2).lower()}" if m else None


class BezrealitkyAdapter(Adapter):
    """GraphQL api.bezrealitky.cz. Dotaz NEOVEREN. Pozn.: robots.txt api.bezrealitky.cz i /vyhledat*
    na www zakazuji -> PoliteClient pozadavek odmitne (stav 2026-10-18)."""
    name = "bezrealitky"
    API = "https://api.bezrealitky.cz/graphql/"
    QUERY = """query L($t:[OfferType],$o:Int){listAdverts(offerType:$t,estateType:[BYT],regionOsmIds:["R435514"],limit:50,offset:$o,order:TIMEORDER_DESC){
      list{id disposition surface price gps{lat lng} penb timeActivated}}}"""

    def endpoints(self, offer_type):
        return [self.API]

    def fetch(self, client, offer_type, max_pages):
        t = {"rent": "PRONAJEM", "sale": "PRODEJ"}[offer_type]
        for page in range(max_pages):
            r = client.request("POST", self.API, json={"query": self.QUERY, "variables": {"t": [t], "o": page * 50}})
            if r is None:
                return
            items = (((r.json().get("data") or {}).get("listAdverts") or {}).get("list")) or []
            if not items:
                return
            for a in items:
                gps = a.get("gps") or {}
                ts = a.get("timeActivated")
                yield make_listing(
                    self.name, a.get("id"), offer_type=offer_type,
                    disposition=_disp((a.get("disposition") or "").replace("DISP_", "").replace("_", "+")),
                    area_m2=_num(a.get("surface")), price_czk=a.get("price"), city="Praha", district=None,
                    lat=_num(gps.get("lat")), lng=_num(gps.get("lng")), penb=(a.get("penb") or None),
                    published_at=datetime.fromtimestamp(int(ts), timezone.utc).isoformat() if ts else None,
                )


class NotVerifiedAdapter(Adapter):
    """Portal prosel kontrolou podminek, ale parser neni overen na realne odpovedi.
    Nic nestahuje, dokud ho nekdo po schvaleni behu neoveri na jedne strance."""
    def __init__(self, name, endpoint):
        self.name, self._ep = name, endpoint

    def endpoints(self, offer_type):
        return [self._ep.format(t={"rent": "pronajem", "sale": "prodej"}[offer_type])]

    def fetch(self, client, offer_type, max_pages):
        raise NotImplementedError(f"{self.name}: parser neoveren - nespoustim")
        yield  # pragma: no cover


ADAPTERS = {a.name: a for a in (
    BezrealitkyAdapter(),
    NotVerifiedAdapter("idnes", "https://reality.idnes.cz/s/{t}/byty/praha/"),
    NotVerifiedAdapter("eurobydleni", "https://www.eurobydleni.cz/byty/praha/{t}/"),
) if a.name in ALLOWED}


# ---------------------------------------------------------------- beh
def decision_ok(cfg: dict) -> bool:
    """Portal smi bezet jen kdyz existuje soubor s pravnim verdiktem obsahujici 'VERDIKT: POVOLENO'."""
    p = (HERE / cfg.get("decision_file", "")).resolve()
    if not p.is_file():
        return False
    t = p.read_text(encoding="utf-8", errors="ignore")
    # rozhodnuti: "Pred behem: schvaleni advokatem a vlastnikem" -> oba radky musi existovat
    return "VERDIKT: POVOLENO" in t and "SCHVÁLENO ADVOKÁTEM:" in t


def night_window() -> bool:
    """Beh jen 01-05 h Europe/Prague (rozhodnuti, technicka pravidla)."""
    from zoneinfo import ZoneInfo
    return 1 <= datetime.now(ZoneInfo("Europe/Prague")).hour < 5


def is_recent(listing: Listing, max_age_days: int) -> bool:
    if not listing.published_at:
        return False  # bez data zverejneni nelze overit 30denni okno => vyradit
    try:
        dt = datetime.fromisoformat(listing.published_at.replace("Z", "+00:00"))
    except ValueError:
        return False
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt >= datetime.now(timezone.utc) - timedelta(days=max_age_days)


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="ONhost Reality Research scraper")
    ap.add_argument("--config", default=str(HERE / "config.json"))
    ap.add_argument("--out", default="listings.jsonl")
    ap.add_argument("--type", choices=["rent", "sale", "both"], default="both")
    ap.add_argument("--max-pages", type=int, default=1)
    ap.add_argument("--budget", type=int, default=300, help="max pozadavku celkem napric portaly")
    ap.add_argument("--dry-run", action="store_true",
                    help="stahne jen robots.txt a vypise, co by se stahovalo")
    args = ap.parse_args(argv)

    cfg = json.loads(Path(args.config).read_text(encoding="utf-8"))
    enabled = [n for n, c in cfg.get("portals", {}).items()
               if c.get("enabled") and n in ADAPTERS and n in ALLOWED and n not in FORBIDDEN]
    if not enabled:
        print("Zadny portal neni povolen (vychozi stav). Viz config.json a rozhodnuti.", file=sys.stderr)
        if not args.dry_run:
            return 0
    if enabled and not args.dry_run and not decision_ok(cfg):
        print("Chybi pravni verdikt 'VERDIKT: POVOLENO' v souboru rozhodnuti - koncim.", file=sys.stderr)
        return 2
    if enabled and not args.dry_run and not night_window():
        print("Beh je povolen jen 01-05 h Europe/Prague - koncim.", file=sys.stderr)
        return 2
    if requests is None:
        print("Chybi knihovna requests (pip install requests).", file=sys.stderr)
        return 2

    client = PoliteClient(cfg.get("min_interval_seconds", MIN_INTERVAL), dry_run=args.dry_run, budget=args.budget)
    types = ["rent", "sale"] if args.type == "both" else [args.type]
    targets = list(ADAPTERS) if (args.dry_run and not enabled) else enabled

    if args.dry_run:
        for name in targets:
            for t in types:
                for url in ADAPTERS[name].endpoints(t):
                    try:
                        ok = client.allowed(url)
                    except Exception as e:  # noqa
                        ok = f"chyba: {e}"
                    state = "povoleno" if name in enabled else "VYPNUTO"
                    print(f"{name:12} {t:4} [{state}] robots={'ANO' if ok is True else ok} {url}")
        return 0

    n = 0
    with open(args.out, "a", encoding="utf-8") as fh:
        for name in enabled:
            for t in types:
                try:
                    for l in ADAPTERS[name].fetch(client, t, args.max_pages):
                        if is_recent(l, cfg.get("max_age_days", 30)):
                            fh.write(json.dumps(asdict(l), ensure_ascii=False) + "\n")
                            n += 1
                except PermissionError as e:
                    print(e, file=sys.stderr)
                except Exception as e:  # noqa
                    print(f"{name}/{t}: {e}", file=sys.stderr)
    print(f"Zapsano {n} zaznamu do {args.out}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
