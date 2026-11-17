#!/usr/bin/env bash
# Construit les archives installables dans dist/ (thème + extension).
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/galsenfoot-theme.zip dist/galsenfoot-league.zip
zip -rqX dist/galsenfoot-theme.zip galsenfoot -x '*.DS_Store'
zip -rqX dist/galsenfoot-league.zip galsenfoot-league -x '*.DS_Store'
ls -la dist
