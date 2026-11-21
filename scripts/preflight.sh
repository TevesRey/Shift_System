#!/usr/bin/env bash
# Builds the exact image Vercel will build and smoke-tests it locally.
# Needs only Docker. Run:  bash scripts/preflight.sh
set -euo pipefail
NAME=shiftmate-preflight
cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "1/4 Building image (this is the step that catches Composer/PHP errors)..."
docker build -t shiftmate-preflight .

echo "2/4 Checking PHP version and required extensions..."
docker run --rm shiftmate-preflight php -r '
  if (PHP_VERSION_ID < 80401) { fwrite(STDERR, "PHP too old: ".PHP_VERSION."\n"); exit(1); }
  foreach (["pdo_mysql","mbstring","intl","zip","openssl","tokenizer","xml","dom","curl","fileinfo","ctype"] as $e)
    if (!extension_loaded($e)) { fwrite(STDERR, "Missing extension: $e\n"); exit(1); }
  echo "PHP ".PHP_VERSION." OK, all extensions present\n";'

echo "3/4 Booting the app (no database needed for this test)..."
KEY=$(docker run --rm shiftmate-preflight php artisan key:generate --show)
docker run -d --name "$NAME" -p 8080:80 \
  -e APP_KEY="$KEY" -e SESSION_DRIVER=array -e CACHE_STORE=array \
  shiftmate-preflight >/dev/null
for i in $(seq 1 30); do
  curl -fs -o /dev/null http://localhost:8080/up && break
  sleep 1
  [ "$i" = 30 ] && { echo "App did not start. Logs:"; docker logs "$NAME"; exit 1; }
done

echo "4/4 Requesting pages..."
for path in /up /; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:8080$path")
  echo "  $path -> $code"
  [ "$code" = 200 ] || { docker logs "$NAME"; exit 1; }
done
echo
echo "PREFLIGHT PASSED: the image builds and serves ShiftMate. Safe to deploy."
