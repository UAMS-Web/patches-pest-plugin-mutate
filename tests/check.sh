#!/usr/bin/env bash
# Installs the pinned upstream into a temp tree, applies every patch composer.json lists, in
# order, and checks that the mutant-timeout patch changes the limit a mutant child gets.
#
#   tests/check.sh                    apply all patches and run the behavior check
#   SKIP_PATCH=<file> tests/check.sh  leave one patch out (the negative control)
#   PATCH_DIR=<dir> tests/check.sh        apply the patches from another directory (the drift control)
#
# Red (non-zero) when a patch fails to apply, or when a figure in tests/timeout.php is wrong.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# The package itself is not installed, so its patches stay in $root and are applied by hand.
php -r '
$c = json_decode(file_get_contents($argv[1]), true);
echo json_encode(["require" => ["pestphp/pest-plugin-mutate" => getenv("UPSTREAM_VERSION") ?: $c["require"]["pestphp/pest-plugin-mutate"], "pestphp/pest" => "^5.0.4", "antecedent/patchwork" => "2.2.4"], "config" => ["allow-plugins" => false]]);
' "$root/composer.json" > "$work/composer.json"

(cd "$work" && composer update --no-interaction --no-plugins --no-scripts --quiet)

tree="$work/vendor/pestphp/pest-plugin-mutate"
version="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["require"]["pestphp/pest-plugin-mutate"];' "$root/composer.json")"; version="${UPSTREAM_VERSION:-$version}"
echo "upstream pestphp/pest-plugin-mutate $version"

patches="$(php -r '
foreach (json_decode(file_get_contents($argv[1]), true)["extra"]["patches"]["pestphp/pest-plugin-mutate"] as $p) {
    echo basename($p), "\n";
}' "$root/composer.json")"

applied=0
for patch in $patches; do
    if [ -n "${SKIP_PATCH:-}" ] && [ "$patch" = "$SKIP_PATCH" ]; then
        echo "skip  $patch"
        continue
    fi
    # CRLF would make a context line differ; the patches are LF (.gitattributes: -text).
    if ! (cd "$tree" && git apply -p1 --ignore-whitespace "${PATCH_DIR:-$root/patches}/$patch") 2>"$work/err"; then
        echo "FAIL  $patch does not apply to upstream $version" >&2
        cat "$work/err" >&2
        exit 1
    fi
    echo "apply $patch"
    applied=$((applied + 1))
done
echo "$applied patches applied"

for script in timeout crash patchwork; do
    php -r 'require $argv[1]; exit(require $argv[2]);' "$work/vendor/autoload.php" "$root/tests/$script.php" 2>&1 || {
        status=$?
        exit "$status"
    }
done
