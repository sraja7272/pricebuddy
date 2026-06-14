"""CloakBrowser adapter for PriceBuddy.

Implements the small subset of the `jez500/seleniumbase-scrapper` REST contract
that `jez500/web-scraper-for-laravel`'s WebScraperApi relies on:

    GET /api/article?url=...&wait-until=...&timeout=...&sleep=...
                     &extra-http-headers=Cookie:...&device=...&full-content=...&cache=...

    -> {"fullContent": "<html>...</html>"}

This lets PriceBuddy keep using its existing `scraper_service=api` flow while the
rendering is done by CloakBrowser (a stealth Chromium) instead of seleniumbase.
"""

import logging
import os
import time
from typing import Dict, Optional

from cloakbrowser import launch
from fastapi import FastAPI, Query
from fastapi.responses import JSONResponse
from playwright.sync_api import TimeoutError as PlaywrightTimeoutError

logger = logging.getLogger("uvicorn.error")

app = FastAPI(title="cloakbrowser-scraper-adapter")

VALID_WAIT_UNTIL = {"load", "domcontentloaded", "networkidle", "commit"}
DEFAULT_WAIT_UNTIL = "networkidle"

# The `timeout` param is also the *caller's* (PHP) request timeout, which starts
# ticking before our browser launch overhead. Reserve some of it as headroom so we
# always respond before the caller gives up, and never let the remaining budget
# drop below a sane floor for Playwright's own timeout.
RESPONSE_OVERHEAD_BUFFER_MS = 8000
MIN_REMAINING_MS = 1000

# Many sites (eg. BestBuy) never go fully `networkidle`/`load` due to ongoing ads,
# analytics, or chat-widget polling. Cap the extra wait beyond `domcontentloaded` so
# such sites don't burn the entire remaining budget waiting for something that will
# never happen - by the time `domcontentloaded` fires, an SPA has usually already
# hydrated its content.
MAX_EXTRA_WAIT_MS = 8000

# Stealth defaults can be tuned via env without rebuilding the image.
HUMANIZE = os.environ.get("CLOAK_HUMANIZE", "true").lower() != "false"
GEOIP = os.environ.get("CLOAK_GEOIP", "true").lower() != "false"
PROXY = os.environ.get("CLOAK_PROXY") or None


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.get("/api/article")
def article(
    url: str,
    wait_until: str = Query(DEFAULT_WAIT_UNTIL, alias="wait-until"),
    timeout: int = 30000,
    sleep: int = 0,
    extra_http_headers: Optional[str] = Query(None, alias="extra-http-headers"),
    # Accepted for contract compatibility with WebScraperApi but not used directly:
    # 'device' selects a UA/viewport preset in seleniumbase, 'full-content' always
    # returns the full HTML here, and 'cache' is handled on the Laravel side.
    device: Optional[str] = None,
    full_content: Optional[str] = Query(None, alias="full-content"),
    cache: Optional[str] = None,
) -> dict:
    if wait_until not in VALID_WAIT_UNTIL:
        wait_until = DEFAULT_WAIT_UNTIL

    headers = _parse_extra_http_headers(extra_http_headers)

    start = time.monotonic()
    budget_ms = max(timeout - RESPONSE_OVERHEAD_BUFFER_MS, MIN_REMAINING_MS)

    def remaining_ms() -> int:
        elapsed = (time.monotonic() - start) * 1000
        return max(int(budget_ms - elapsed), MIN_REMAINING_MS)

    browser = None
    try:
        browser = launch(humanize=HUMANIZE, geoip=GEOIP, proxy=PROXY)
        context = browser.new_context(extra_http_headers=headers) if headers else browser.new_context()
        try:
            page = context.new_page()

            # `domcontentloaded` is fast and reliable. Heavy sites (eg. BestBuy) keep
            # background connections open (ads, analytics, chat widgets) and never
            # reach `networkidle`/`load` within a sane timeout, so treat those as a
            # best-effort extra wait on top of `domcontentloaded` rather than a hard
            # requirement - we still return whatever HTML has rendered so far.
            page.goto(url, wait_until="domcontentloaded", timeout=remaining_ms())

            if wait_until in ("load", "networkidle"):
                try:
                    page.wait_for_load_state(wait_until, timeout=min(remaining_ms(), MAX_EXTRA_WAIT_MS))
                except PlaywrightTimeoutError:
                    logger.info("Timed out waiting for '%s' on %s, returning current content", wait_until, url)

            if sleep:
                page.wait_for_timeout(min(sleep, remaining_ms()))

            html = page.content()
        finally:
            context.close()

        return {"fullContent": html}
    except Exception as exc:  # noqa: BLE001 - surface all render failures to the caller
        logger.exception("Failed to render %s", url)
        # Return 200 with an empty fullContent so WebScraperApi's
        # `data_get($json, 'fullContent', '')` check triggers its normal
        # "No content found" retry path instead of a connection exception.
        return JSONResponse(content={"fullContent": "", "error": str(exc)})
    finally:
        if browser:
            browser.close()


def _parse_extra_http_headers(raw: Optional[str]) -> Dict[str, str]:
    """Parse the `Header:value` string sent by WebScraperApi (currently only `Cookie:...`)."""
    if not raw:
        return {}

    name, _, value = raw.partition(":")
    name, value = name.strip(), value.strip()

    return {name: value} if name and value else {}
