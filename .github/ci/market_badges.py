#!/usr/bin/env python3
"""Regenerate the Jeedom market badge block in README.md from market.jeedom.com.

Runs from the repository root. The market id is resolved from plugin_info/info.json
(name, then id) through the public market catalogue, unless .github/ci/ci-config.json
provides "market": {"id": <n>}.

The generated block is delimited by HTML comment markers, so the script is
idempotent and never touches the rest of the README. Any network, parsing or
lookup problem leaves README.md untouched and exits 0: a badge refresh must
never turn a repository red.
"""
from __future__ import annotations

import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from functools import lru_cache
from pathlib import Path

MARKET = "https://market.jeedom.com"
DISPLAY = MARKET + "/index.php?v=d&p=market_display&id="
AJAX = MARKET + "/core/ajax/market.ajax.php"
START = "<!-- market-badges:start -->"
END = "<!-- market-badges:end -->"
UA = {"User-Agent": "jeedom-plugin-market-badges/1.0 (+https://github.com/Flobul)"}
RATING_COLORS = ((4.5, "brightgreen"), (3.5, "green"), (2.5, "yellow"), (1.5, "orange"))
CARD = r'data-market_id="(\d+)"\s+data-market_type="([^"]*)"(.*?)(?=<div class="market cursor"|$)'


def fetch(url: str, data: bytes | None = None) -> str:
    request = urllib.request.Request(url, data=data, headers=dict(UA))
    if data is not None:
        request.add_header("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
        request.add_header("X-Requested-With", "XMLHttpRequest")
    with urllib.request.urlopen(request, timeout=30) as response:
        return response.read().decode("utf-8", "replace")


def normalize(value) -> str:
    return re.sub(r"[^a-z0-9]", "", str(value or "").lower())


def escape(value) -> str:
    return urllib.parse.quote(str(value).replace("-", "--").replace("_", "__"), safe="")


def badge(label: str, message: str, color: str, link: str) -> str:
    url = f"https://img.shields.io/badge/{escape(label)}-{escape(message)}-{color}?style=flat-square"
    return f"[![{label}]({url})]({link})"


def market_cards(query: str) -> list[dict]:
    """Search the public market catalogue and return the plugin cards it renders."""
    page = fetch(f"{MARKET}/index.php?v=d&p=market&name={urllib.parse.quote(str(query))}")
    cards = []
    for match in re.finditer(CARD, page, re.S):
        block = match.group(3)
        name = re.search(r'class="plugin-name">([^<]*)<', block)
        author = re.search(r'class="plugin-author">.*?</span>\s*([^<]*)<', block, re.S)
        cards.append({
            "id": int(match.group(1)),
            "type": match.group(2),
            "name": (name.group(1).strip() if name else ""),
            "author": (author.group(1).strip() if author else ""),
        })
    return [card for card in cards if card["type"] == "plugin"]


def resolve_market_id(info: dict, config: dict) -> tuple[int | None, str]:
    override = (config.get("market") or {}).get("id")
    if override:
        try:
            market_id = int(override)
        except (TypeError, ValueError):
            return None, f"invalid market.id override {override!r}"
        try:
            logical_id = normalize(plugin_data(market_id).get("logicalId"))
        except (urllib.error.URLError, TimeoutError, RuntimeError, ValueError) as error:
            return None, f"market data unavailable for override id {market_id} ({error})"
        identity = normalize(info.get("id"))
        if not logical_id or logical_id != identity:
            return None, (
                f"logicalId mismatch for override id {market_id}: "
                f"expected {identity!r}, got {logical_id!r}"
            )
        return market_id, f"ci-config override, logicalId {identity!r} confirmed"

    # The market exposes the plugin's own logicalId, which is exactly the `id`
    # from plugin_info/info.json. Confirming it (not the display name) is what
    # makes the match trustworthy: 'LG ThinQ Local' must never inherit the
    # badges of 'LG ThinQ'.
    identity = normalize(info.get("id"))
    name = info.get("name")
    key = normalize(name)

    def plausible(card: dict) -> bool:
        """Cheap admission filter before spending a request on the logicalId."""
        card_name = normalize(card["name"])
        return card_name == key or card_name == identity or \
            card_name.startswith(key) or key.startswith(card_name)

    seen: set[int] = set()
    lookup_errors: list[str] = []
    for query in (name, info.get("id")):
        if not query:
            continue
        try:
            cards = market_cards(query)
        except (urllib.error.URLError, TimeoutError) as error:
            return None, f"market unreachable ({error})"
        exact = []
        for card in cards:
            if card["id"] in seen or not plausible(card):
                continue
            seen.add(card["id"])
            try:
                logical = normalize(plugin_data(card["id"]).get("logicalId"))
            except (urllib.error.URLError, TimeoutError, RuntimeError, ValueError) as error:
                lookup_errors.append(f"id {card['id']}: {error}")
                continue
            if logical and logical == identity:
                exact.append(card)
        if len(exact) == 1:
            return exact[0]["id"], f"logicalId {identity!r} confirmed on {exact[0]['name']!r}"
        if len(exact) > 1:
            return None, f"ambiguous, candidates {[card['id'] for card in exact]}"
    if lookup_errors:
        return None, "market data unavailable for candidate(s): " + "; ".join(lookup_errors)
    return None, f"not published (name={name!r}, id={info.get('id')!r})"


@lru_cache(maxsize=128)
def plugin_data(market_id: int) -> dict:
    payload = urllib.parse.urlencode({"action": "get", "id": market_id}).encode()
    document = json.loads(fetch(AJAX, payload))
    if document.get("state") != "ok":
        raise RuntimeError(f"state={document.get('state')} result={document.get('result')}")
    result = document.get("result")
    if not isinstance(result, dict):
        raise RuntimeError(f"invalid result type {type(result).__name__}")
    return result


def number(value) -> float | None:
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def count(value: float) -> str:
    return f"{value:,.0f}".replace(",", " ")


def money(amount: float) -> str:
    if abs(amount - round(amount)) < 0.005:
        return f"{amount:.0f} €"
    return f"{amount:.2f} €".replace(".", ",")


def badges(data: dict) -> list[str]:
    link = DISPLAY + str(data["id"])
    lines = []

    rate = data.get("rate") or {}
    rating, votes = number(rate.get("rating")), number(rate.get("nb")) or 0
    if rating is not None and votes > 0:
        color = next((color for threshold, color in RATING_COLORS if rating >= threshold), "red")
        lines.append(badge("Note", f"{rating:g}/5", color, link))
    else:
        lines.append(badge("Note", "Aucun avis", "lightgrey", link))

    cost, discount = number(data.get("cost")), number(data.get("discountCost"))
    lines.append(badge("Prix", money(cost) if cost else "Gratuit", "blue" if cost else "brightgreen", link))
    if cost and discount and discount < cost:
        lines.append(badge("Promo", money(discount), "orange", link))

    buyers = number(data.get("nbBuyer"))
    if buyers is None:
        buyers = number(data.get("buyer"))
    if buyers is not None:
        lines.append(badge("Achats", count(buyers), "blueviolet", link))

    downloads = number(data.get("downloaded"))
    if downloads is not None:
        lines.append(badge("Téléchargements", count(downloads), "informational", link))

    comments = number(data.get("nbComment"))
    if comments is not None:
        lines.append(badge("Commentaires", count(comments), "lightgrey", link))

    return lines


def is_badge_line(line: str) -> bool:
    return bool(re.match(r"^\s*\[!\[[^\]]*\]\([^)]*\)\]\([^)]*\)\s*$", line))


def render(readme: str, lines: list[str]) -> str:
    """Replace the marked block, or insert one after the existing shield badges."""
    block = [START, *lines, END]
    text_lines = readme.splitlines()
    start_count, end_count = text_lines.count(START), text_lines.count(END)
    if start_count != end_count or start_count > 1:
        raise RuntimeError("invalid market badge markers (expected one ordered pair)")
    if start_count == 1:
        start, end = text_lines.index(START), text_lines.index(END)
        if start >= end:
            raise RuntimeError("invalid market badge markers (expected one ordered pair)")
        merged = text_lines[:start] + block + text_lines[end + 1:]
    else:
        last = max((index for index, line in enumerate(text_lines[:40]) if is_badge_line(line)), default=None)
        if last is None:
            raise RuntimeError("no shield badge line found in the first 40 README lines")
        merged = text_lines[:last + 1] + ["", *block] + text_lines[last + 1:]
    return "\n".join(merged) + "\n"


def main() -> int:
    root = Path(".").resolve()
    readme_path, info_path = root / "README.md", root / "plugin_info" / "info.json"
    if not readme_path.exists() or not info_path.exists():
        print("::warning::README.md or plugin_info/info.json missing, nothing to do")
        return 0

    info = json.loads(info_path.read_text(encoding="utf-8"))
    config_path = root / ".github" / "ci" / "ci-config.json"
    config = json.loads(config_path.read_text(encoding="utf-8")) if config_path.exists() else {}

    market_id, reason = resolve_market_id(info, config)
    if market_id is None:
        level = "::notice::" if reason.startswith("not published") else "::warning::"
        print(f"{level}no Jeedom market entry for {info.get('id')!r}: {reason}; README untouched")
        return 0

    try:
        data = plugin_data(market_id)
    except (urllib.error.URLError, TimeoutError, RuntimeError, ValueError) as error:
        print(f"::warning::market data unavailable for id {market_id} ({error}); README untouched")
        return 0

    print(f"market id {market_id} ({data.get('name')}) resolved by {reason}")
    current = readme_path.read_text(encoding="utf-8")
    try:
        updated = render(current, badges(data))
    except RuntimeError as error:
        print(f"::warning::{error}; README untouched")
        return 0
    if updated == current:
        print("README.md already up to date")
        return 0
    readme_path.write_text(updated, encoding="utf-8")
    print("README.md updated")
    return 0


if __name__ == "__main__":
    sys.exit(main())
