# 1. Alapréteg: PHP 8.3 (CLI változat, mert nem webszerver, hanem parancs fut)
FROM php:8.3-cli-alpine

# 2. Rendszer-függőségek + a Composer behozása
RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Munkakönyvtár
WORKDIR /app

# 4. Előbb csak a composer fájlok (a cache-optimalizálás miatt, mint az npm-nél)
COPY composer.json composer.lock ./

# 5. Függőségek telepítése (production módban, dev csomagok nélkül)
RUN composer install --no-dev --no-scripts --no-autoloader

# 6. A többi fájl bemásolása
COPY . .

# 7. A composer autoloader befejezése
RUN composer dump-autoload --optimize

# 8. Az indító parancs: a NATS-figyelő
CMD ["php", "artisan", "nats:listen-orders"]