# CloakBrowser-based replacement for jez500/seleniumbase-scrapper.
#
# Exposes the same `GET /api/article` contract (see app.py) so PriceBuddy's
# SCRAPER_BASE_URL / `scraper_service=api` flow needs no changes.
FROM cloakhq/cloakbrowser

WORKDIR /app

# Don't re-check for browser binary updates at runtime — the binary is
# baked into the image at build time below.
ENV CLOAKBROWSER_AUTO_UPDATE=false

COPY docker/scraper/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download the Chromium binary at build time so the container is
# self-contained and the first request isn't slowed by a ~200MB download.
RUN python -m cloakbrowser install

COPY docker/scraper/app.py .

EXPOSE 3000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "3000"]
