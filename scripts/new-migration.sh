#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <table_name>" >&2
    echo "Example: $0 blocks" >&2
    exit 1
fi

table_name="$1"
date_prefix="$(date +%Y_%m_%d)"
migration_dir="$(cd "$(dirname "$0")/../database/migrations" && pwd)"

latest_number="$(
    find "$migration_dir" -maxdepth 1 -type f -name '*.php' \
        | sed -E 's#.*/[0-9]{4}_[0-9]{2}_[0-9]{2}_([0-9]{6})_.*#\1#' \
        | sort -n \
        | tail -n 1
)"

if [[ -z "${latest_number}" ]]; then
    latest_number=0
fi

next_number=$(printf '%06d' "$((10#${latest_number} + 1))")

slug="$(printf '%s' "$table_name" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/_/g; s/^_+//; s/_+$//')"
slug="${slug%_table}"
filename="${date_prefix}_${next_number}_create_${slug}_table.php"
filepath="${migration_dir}/${filename}"

if [[ -e "$filepath" ]]; then
    echo "Migration already exists: $filepath" >&2
    exit 1
fi

cat > "$filepath" <<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('${slug}', function (Blueprint \$table): void {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('${slug}');
    }
};
PHP

echo "$filepath"
