#!/usr/bin/env bash
# Builds installable zips in dist/ (Extensions → Ajouter → Téléverser / Apparence → Thèmes → Téléverser).
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/eternelle.zip dist/wedding-rsvp-pass.zip
(cd themes && zip -qr ../dist/eternelle.zip eternelle -x '*.DS_Store')
(cd plugins && zip -qr ../dist/wedding-rsvp-pass.zip wedding-rsvp-pass -x '*.DS_Store')
ls -lh dist
