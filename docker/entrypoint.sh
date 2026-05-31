#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

exec "$@"
