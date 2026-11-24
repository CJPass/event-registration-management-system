#!/bin/bash
# ==================================================================
# tests/db_failure_tests.sh — what visitors see when the database fails
# ------------------------------------------------------------------
# Temporarily edits config/config.php (a copy is kept and ALWAYS put
# back, even if the script stops), and for each failure checks the
# page in debug mode (XAMPP) and production mode (InfinityFree).
#
#   bash tests/db_failure_tests.sh --yes
#
# Appends results to tests/output/results.tsv (run run_tests.sh first
# if you want both in one file).
# ==================================================================
set -u
cd "$(dirname "$0")/.."
B=${BASE:-http://localhost/event-registration}
MYSQL=${MYSQL:-/c/xampp/mysql/bin/mysql.exe}
CFG=config/config.php
OUT=tests/output; mkdir -p "$OUT"
RES="$OUT/results.tsv"
[ -f "$RES" ] || printf 'id\tarea\ttest\tdata\texpected\tactual\tstatus\n' > "$RES"
sed -i '/^DBF-/d' "$RES"
[ "${1:-}" = "--yes" ] || { read -r -p "This temporarily breaks config/config.php and restores it. Continue? [y/N] " a; [ "$a" = y ] || exit 1; }

cp "$CFG" "$OUT/config.php.bak"
cleanup() {
    cp "$OUT/config.php.bak" "$CFG" && rm -f "$OUT/config.php.bak" && echo "config/config.php restored"
    "$MYSQL" -uroot -e "DROP DATABASE IF EXISTS ers_empty_test" 2>/dev/null
}
trap cleanup EXIT

PASS=0; FAIL=0
clean() { printf '%s' "$1" | tr '\t\n\r' '   ' | cut -c1-160; }
rec() { printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$1" "DB failure" "$2" "$(clean "$3")" "$(clean "$4")" "$(clean "$5")" "$6" >> "$RES"
        [ "$6" = PASS ] && { PASS=$((PASS+1)); echo "PASS  $1  $2"; } || { FAIL=$((FAIL+1)); echo "FAIL  $1  $2  [expected: $4 | actual: $(clean "$5")]"; }; }

# PHP's OPcache re-reads changed files only every opcache.revalidate_freq (2 s by
# default), so wait 3 s after every config change before requesting a page.
settle() { sleep 3; }
# set_cfg NAME VALUE  (VALUE is PHP code, e.g. "'x'" or false)
# Edits the matching key of the $settings array in config/config.php.
set_cfg() {
    local key
    case $1 in DB_HOST) key=db_host;; DB_NAME) key=db_name;; DB_USER) key=db_user;;
               DB_PASS) key=db_pass;; APP_DEBUG) key=debug;; *) echo "unknown setting $1" >&2; exit 1;; esac
    sed -i "s|^\(    '$key' *=> *\)[^,]*,|\1$2,|" "$CFG"
}
reset_cfg() { cp "$OUT/config.php.bak" "$CFG"; }

# check ID label data mode page needle-in-debug
check() {
    local id=$1 label=$2 data=$3 mode=$4 page=$5 debugneedle=$6
    local body code
    body=$(curl -s -w '\n%{http_code}' "$B/$page"); code=$(printf '%s' "$body" | tail -1); body=$(printf '%s' "$body" | sed '$d')
    local title; title=$(printf '%s' "$body" | grep -oE '<h1[^>]*>[^<]*' | sed 's/.*>//' | head -1)
    local leaks; leaks=$(printf '%s' "$body" | grep -oiE 'SQLSTATE|C:\\\\xampp|C:\\xampp|stack trace|event_registration_db|ers_empty_test|root@|3399|Fatal error' | sort -u | paste -sd, -)
    if [ "$mode" = production ]; then
        local actual="HTTP $code, title=\"$title\", leaks=[${leaks}]"
        [ "$code" = 500 ] && [ -n "$title" ] && [ -z "$leaks" ] && s=PASS || s=FAIL
        rec "$id" "$label ($mode): $page" "$data, APP_DEBUG=false" "HTTP 500, friendly title, no technical details" "$actual" $s
    else
        local has=no; [[ "$body" == *"$debugneedle"* ]] && has=yes
        local actual="HTTP $code, title=\"$title\", reason shown=$has"
        [ "$code" = 500 ] && [ -n "$title" ] && [ $has = yes ] && s=PASS || s=FAIL
        rec "$id" "$label ($mode): $page" "$data, APP_DEBUG=true" "HTTP 500, friendly title + reason for the developer" "$actual" $s
    fi
}

n=1
scenario() { # label data cfg-name cfg-value debug-needle
    for mode in debug production; do
        reset_cfg; set_cfg "$3" "$4"
        [ $mode = production ] && set_cfg APP_DEBUG false
        settle
        for page in index.php admin/login.php; do
            check "DBF-$(printf %02d $n)" "$1" "$2" $mode $page "$5"; n=$((n+1))
        done
    done
}

scenario "Unknown database"     "DB_NAME='no_such_db'"            DB_NAME "'no_such_db'"          "Unknown database"
scenario "Wrong password"       "DB_PASS='wrong-password'"        DB_PASS "'wrong-password'"      "Access denied"
scenario "MySQL not reachable"  "DB_HOST='127.0.0.1;port=3399'"   DB_HOST "'127.0.0.1;port=3399'" "Database connection failed"

# Connected, but the tables are missing (e.g. SQL file not imported)
"$MYSQL" -uroot -e "CREATE DATABASE IF NOT EXISTS ers_empty_test"
scenario "Tables missing"       "DB_NAME='ers_empty_test' (empty database)" DB_NAME "'ers_empty_test'" "Base table or view not found"

reset_cfg; settle
code=$(curl -s -o /dev/null -w '%{http_code}' "$B/index.php")
[ "$code" = 200 ] && s=PASS || s=FAIL
rec "DBF-$(printf %02d $n)" "Site works again with the original config" "config/config.php restored" "HTTP 200" "HTTP $code" $s; n=$((n+1))

logline=$(tail -50 /c/xampp/apache/logs/error.log 2>/dev/null | grep -c "Database connection failed")
[ "$logline" -gt 0 ] && s=PASS || s=FAIL
rec "DBF-$(printf %02d $n)" "Failures are written to the server error log" "apache/logs/error.log" "'Database connection failed' lines present" "$logline lines" $s

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
