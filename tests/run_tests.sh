#!/bin/bash
# ==================================================================
# tests/run_tests.sh — automated end-to-end tests (XAMPP, Git Bash)
# ------------------------------------------------------------------
# Drives the real website with curl and checks the real database with
# the mysql client. Covers login, access control, CRUD, validation,
# duplicates, capacity, cancelled events, search, SQL injection, XSS,
# CSRF, security headers and foreign keys.
#
#   bash tests/run_tests.sh           (asks before touching the database)
#   bash tests/run_tests.sh --yes     (no question)
#
# WHAT IT DOES TO YOUR DATABASE
#   1. Backs up event_registration_db to tests/output/backup-<time>.sql
#   2. Re-imports database/event_registration.sql (sample data, no admins)
#   3. Runs the tests (creates/edits/deletes rows, creates a test admin)
#   4. Restores the backup, so your data is exactly as before
#
# Results: tests/output/results.tsv (one line per test case)
# Do NOT upload the tests/ folder to InfinityFree.
# ==================================================================
set -u
cd "$(dirname "$0")/.."
ROOT=$(pwd)
B=${BASE:-http://localhost/event-registration}
MYSQL=${MYSQL:-/c/xampp/mysql/bin/mysql.exe}
DUMP=${MYSQLDUMP:-/c/xampp/mysql/bin/mysqldump.exe}
SESS_DIR=${SESS_DIR:-/c/xampp/tmp}
DB=event_registration_db
OUT="$ROOT/tests/output"; mkdir -p "$OUT"
W="$OUT/work"; mkdir -p "$W"; rm -f "$W"/*.jar
RES="$OUT/results.tsv"
printf 'id\tarea\ttest\tdata\texpected\tactual\tstatus\n' > "$RES"

if [ "${1:-}" != "--yes" ]; then
    read -r -p "This resets $DB during the test and restores it afterwards. Continue? [y/N] " a
    [ "$a" = "y" ] || exit 1
fi

# ---------- helpers ----------
sql()   { "$MYSQL" -uroot -N -B "$DB" -e "$1" 2>&1; }
PASS=0; FAIL=0
clean() { printf '%s' "$1" | tr '\t\n\r' '   ' | cut -c1-160; }
rec() { # id area test data expected actual status
    printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "$(clean "$4")" "$(clean "$5")" "$(clean "$6")" "$7" >> "$RES"
    if [ "$7" = PASS ]; then PASS=$((PASS+1)); printf 'PASS  %-9s %s\n' "$1" "$3"
    else FAIL=$((FAIL+1)); printf 'FAIL  %-9s %s  [expected: %s | actual: %s]\n' "$1" "$3" "$(clean "$5")" "$(clean "$6")"; fi
}
eq()  { [ "$6" == "$5" ] && rec "$@" PASS || rec "$@" FAIL; }                 # actual == expected
has() { # like eq, but passes when the page/output CONTAINS the expected text
    if [[ "$6" == *"$5"* ]]; then rec "$1" "$2" "$3" "$4" "contains: $5" "found: $5" PASS
    else rec "$1" "$2" "$3" "$4" "contains: $5" "not found; got: $(printf '%s' "$6" | sed 's/<[^>]*>//g' | tr -s ' 
' ' ' | cut -c1-120)" FAIL; fi; }
tok() { curl -s -c "$1" -b "$1" "$B/$2" | sed -n 's/.*name="csrf_token" value="\([0-9a-f]*\)".*/\1/p' | head -1; }
go()  { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$1" -b "$1" "${@:2}"; }   # status + Location
get() { curl -s -L -c "$1" -b "$1" "${@:2}"; }                                                 # final page body
flash() { tr -d '\n' | grep -oE 'class="alert alert-(success|danger|warning|info)[^"]*"[^>]*>[^<]*' | sed 's/.*>//' | sed 's/^ *//' | head -1; }
ferr() { grep -oE 'invalid-feedback">[A-Z][^<]*' | sed 's/invalid-feedback">//' | paste -sd'|' -; }
phperr() { grep -ciE '(fatal error|warning:|notice:|deprecated:|uncaught|SQLSTATE|stack trace)' ; }
sid() { grep -E '\sERS_SESSION\s' "$1" | awk '{print $7}' | tail -1; }
LOGIN="$B/admin/login.php"
L="$B/admin"

# ---------- 0. backup + fresh database ----------
STAMP=$(date +%Y%m%d-%H%M%S)
"$DUMP" -uroot --databases "$DB" > "$OUT/backup-$STAMP.sql" || { echo "backup failed"; exit 1; }
restore() { [ "${KEEP_DB:-}" = 1 ] && { echo "KEEP_DB=1: database NOT restored (backup: tests/output/backup-$STAMP.sql)"; return; }
  "$MYSQL" -uroot < "$OUT/backup-$STAMP.sql" && echo "Database restored from tests/output/backup-$STAMP.sql"; rm -f "$W"/*.jar "$W"/p.html; }
trap restore EXIT
"$MYSQL" -uroot < database/event_registration.sql || { echo "import failed"; exit 1; }
echo "Backup: tests/output/backup-$STAMP.sql — fresh sample database imported"
ADMIN=tester_$RANDOM
PW="Pw-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
G="$W/guest.jar"; A="$W/admin.jar"; V="$W/visitor.jar"

# ==================================================================
echo "== 1. Administrator login and logout"
eq AUTH-01 Login "No admin yet: /admin/login.php sends to setup" "fresh DB, 0 administrators" "302 $L/setup.php" "$(go $G $LOGIN)"
T=$(tok $G admin/setup.php)
out=$(get $G $L/setup.php --data-urlencode "csrf_token=$T" -d "username=&password=&password_confirm=" | ferr)
eq AUTH-02 Login "Setup rejects empty fields" "username='' password=''" "Username must be 3–50 characters: letters, numbers, dot, dash or underscore.|Password must be at least 8 characters." "$out"
out=$(get $G $L/setup.php --data-urlencode "csrf_token=$T" -d "username=ab&password=12345678&password_confirm=87654321" | ferr)
eq AUTH-03 Login "Setup rejects short username + mismatched passwords" "username=ab, password 12345678 / 87654321" "Username must be 3–50 characters: letters, numbers, dot, dash or underscore.|The two passwords do not match." "$out"
eq AUTH-04 Login "Rejected setups saved nothing" "SELECT COUNT(*) FROM administrators" "0" "$(sql 'SELECT COUNT(*) FROM administrators')"
go $G $L/setup.php --data-urlencode "csrf_token=$T" -d "username=$ADMIN" --data-urlencode "password=$PW" --data-urlencode "password_confirm=$PW" >/dev/null
eq AUTH-05 Login "Setup creates the first administrator" "username=$ADMIN, random 26-char password" "1" "$(sql "SELECT COUNT(*) FROM administrators WHERE username='$ADMIN'")"
H=$(sql "SELECT password_hash FROM administrators WHERE username='$ADMIN'")
[[ "$H" == '$2y$'* && "$H" != *"$PW"* ]] && r=bcrypt-hash-only || r="$H"
eq AUTH-06 Login "Password stored as bcrypt hash, never plain text" "administrators.password_hash" "bcrypt-hash-only" "$r"
eq AUTH-07 Login "Setup refuses once an admin exists" "GET admin/setup.php" "302 $LOGIN" "$(go $G $L/setup.php)"
T=$(tok $A admin/login.php)
eq AUTH-08 Login "Empty username/password" "username='' password=''" "Please enter your username and password." "$(get $A $LOGIN --data-urlencode "csrf_token=$T" -d 'username=&password=' | grep -oE '<div>[A-Z][^<]*</div>' | sed 's/<[^>]*>//g' | head -1)"
eq AUTH-09 Login "Wrong password" "username=$ADMIN password=wrong-password" "Incorrect username or password." "$(get $A $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN&password=wrong-password" | grep -oE '<div>Incorrect[^<]*</div>' | sed 's/<[^>]*>//g')"
eq AUTH-10 Login "Unknown username gives the same message" "username=nobody" "Incorrect username or password." "$(get $A $LOGIN --data-urlencode "csrf_token=$T" -d "username=nobody&password=wrong-password" | grep -oE '<div>Incorrect[^<]*</div>' | sed 's/<[^>]*>//g')"
get $A $LOGIN --data-urlencode "csrf_token=$T" --data-urlencode "username=' OR '1'='1' -- " -d "password=x" >/dev/null
eq AUTH-11 Login "SQL injection in username does not log in" "username=' OR '1'='1' --  password=x" "302 $LOGIN" "$(go $A $L/index.php)"
eq AUTH-12 Login "Failed attempts are recorded" "3 failures above" "3" "$(sql 'SELECT COUNT(*) FROM login_attempts')"
before=$(sid $A)
eq AUTH-13 Login "Correct login goes to dashboard" "username=$ADMIN + correct password" "302 $L/index.php" "$(go $A $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN" --data-urlencode "password=$PW")"
after=$(sid $A)
[ -n "$after" ] && [ "$before" != "$after" ] && r=changed || r="unchanged ($before)"
eq AUTH-14 Login "Session ID regenerated after login" "ERS_SESSION cookie before vs after" "changed" "$r"
eq AUTH-15 Login "Successful login clears failed attempts" "login_attempts rows" "0" "$(sql 'SELECT COUNT(*) FROM login_attempts')"
has AUTH-16 Login "Dashboard shows the signed-in admin" "GET admin/index.php" "Signed in as <strong class=\"text-body\">$ADMIN</strong>" "$(get $A $L/index.php)"
eq AUTH-17 Login "Login page while logged in goes to dashboard" "GET admin/login.php" "302 $L/index.php" "$(go $A $LOGIN)"
ck=$(curl -s -I "$B/index.php" | grep -i '^set-cookie: ERS_SESSION' | tr -d '\r')
[[ "$ck" == *HttpOnly* && "$ck" == *SameSite=Lax* && "$ck" == *"path=/event-registration/"* ]] && r=ok || r="$ck"
eq AUTH-18 Login "Session cookie is HttpOnly, SameSite=Lax, scoped to the app" "Set-Cookie header" "ok" "$r"
fx=$(curl -s -I --cookie "ERS_SESSION=attackerchosenid1234567890" "$B/index.php" | grep -i '^set-cookie: ERS_SESSION' | sed 's/.*ERS_SESSION=\([^;]*\).*/\1/' | tr -d '\r')
[ -n "$fx" ] && [ "$fx" != attackerchosenid1234567890 ] && r=rejected || r="accepted:$fx"
eq AUTH-19 Login "Session fixation: server refuses an ID it did not issue" "Cookie ERS_SESSION=attackerchosenid…" "rejected" "$r"
cp $A "$W/old.jar"; T=$(tok $A admin/index.php)
eq AUTH-20 Login "Logout via GET is refused" "GET admin/logout.php" "302 $L/index.php" "$(go $A $L/logout.php)"
eq AUTH-21 Login "…and the admin is still logged in" "GET admin/index.php" "200 " "$(go $A $L/index.php)"
eq AUTH-22 Login "Logout via POST + CSRF" "POST admin/logout.php" "You have been logged out." "$(get $A $L/logout.php --data-urlencode "csrf_token=$T" | flash)"
eq AUTH-23 Login "Admin pages blocked after logout" "GET admin/index.php" "302 $LOGIN" "$(go $A $L/index.php)"
eq AUTH-24 Login "Old session cookie no longer works after logout" "replay pre-logout cookie" "302 $LOGIN" "$(go "$W/old.jar" $L/index.php)"
# log back in for the rest of the suite
T=$(tok $A admin/login.php); go $A $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN" --data-urlencode "password=$PW" >/dev/null
rm -f "$W/idle.jar"; T=$(tok "$W/idle.jar" admin/login.php)
go "$W/idle.jar" $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN" --data-urlencode "password=$PW" >/dev/null
SF="$SESS_DIR/sess_$(sid "$W/idle.jar")"
if [ -f "$SF" ]; then
    sed -i 's/admin_last_active|i:[0-9]*/admin_last_active|i:1000000000/' "$SF"
    has AUTH-25 Login "Idle 30+ minutes: logged out with message" "admin_last_active set to 2001 in the session file" "inactivity" "$(get "$W/idle.jar" $L/index.php | flash)"
else
    rec AUTH-25 Login "Idle timeout" "session file" "logged out" "session file not found at $SF" FAIL
fi
L2="$W/lock.jar"; T=$(tok $L2 admin/login.php); sql "DELETE FROM login_attempts" >/dev/null
for i in 1 2 3 4 5; do go $L2 $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN&password=bad$i" >/dev/null; done
has AUTH-26 Login "6th try after 5 failures is locked, even with the right password" "5 wrong passwords, then the correct one" "Too many failed attempts" "$(get $L2 $LOGIN --data-urlencode "csrf_token=$T" -d "username=$ADMIN" --data-urlencode "password=$PW")"
eq AUTH-27 Login "…and not logged in" "GET admin/index.php" "302 $LOGIN" "$(go $L2 $L/index.php)"
sql "DELETE FROM login_attempts" >/dev/null

# ==================================================================
echo "== 2. Access protection for admin pages"
n=1
for p in index.php events.php event_add.php "event_view.php?id=1" "event_edit.php?id=1" registrations.php "registration_view.php?id=1" "registration_edit.php?id=1"; do
    eq "ACC-0$n" Access "Guest GET admin/$p" "no session" "302 $LOGIN" "$(go $G "$L/$p")"; n=$((n+1))
done
GT=$(tok $G index.php)   # a guest has a VALID CSRF token for public forms — the login check must still stop them
before=$(sql "SELECT CONCAT((SELECT COUNT(*) FROM events),'/',(SELECT COUNT(*) FROM registrations),'/',(SELECT status FROM registrations WHERE registration_id=2),'/',(SELECT venue FROM events WHERE event_id=1))")
go $G $L/event_add.php --data-urlencode "csrf_token=$GT" -d "event_name=Hack&event_date=2027-01-01&event_time=10:00&venue=X&capacity=5&status=open" >/dev/null
go $G "$L/event_edit.php?id=1" --data-urlencode "csrf_token=$GT" -d "event_name=Hacked&event_date=2027-01-01&event_time=10:00&venue=Hacked&capacity=50&status=open" >/dev/null
go $G $L/event_delete.php --data-urlencode "csrf_token=$GT" -d "event_id=1" >/dev/null
go $G "$L/registration_edit.php?id=2" --data-urlencode "csrf_token=$GT" -d "full_name=Hacked&email=h@x.com&status=cancelled" >/dev/null
go $G $L/registration_status.php --data-urlencode "csrf_token=$GT" -d "registration_id=2&status=confirmed" >/dev/null
go $G $L/registration_delete.php --data-urlencode "csrf_token=$GT" -d "registration_id=2" >/dev/null
eq ACC-09 Access "Guest POSTs to all 6 admin write endpoints change nothing" "add/edit/delete event, edit/status/delete registration with a valid guest CSRF token" "$before" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM events),'/',(SELECT COUNT(*) FROM registrations),'/',(SELECT status FROM registrations WHERE registration_id=2),'/',(SELECT venue FROM events WHERE event_id=1))")"
eq ACC-10 Access "test_connection.php needs login once an admin exists" "guest GET" "302 $LOGIN" "$(go $G $B/test_connection.php)"
k=11
for d in config/config.php config/database.php includes/queries.php includes/auth.php database/event_registration.sql tests/output/backup-$STAMP.sql; do
    eq "ACC-$((k++))" Access "Direct browser access blocked: $d" "GET /$d" "403" "$(curl -s -o /dev/null -w '%{http_code}' "$B/$d")"
done
eq ACC-17 Access "Admin can open the dashboard" "logged-in GET admin/index.php" "200 " "$(go $A $L/index.php)"

# ==================================================================
echo "== 3. Event CRUD through the website"
T=$(tok $A admin/event_add.php)
out=$(get $A $L/event_add.php --data-urlencode "csrf_token=$T" -d "event_name=QA Test Event&description=Created by the test suite&event_date=2027-03-01&event_time=09:30&venue=QA Hall&capacity=2&status=open" | flash)
EA=$(sql "SELECT event_id FROM events WHERE event_name='QA Test Event'")
eq EV-01 Events "CREATE event (INSERT)" "QA Test Event, 2027-03-01 09:30, QA Hall, capacity 2, open" "Event \"QA Test Event\" was created (ID $EA)." "$(printf '%s' "$out" | sed 's/&quot;/"/g')"
eq EV-02 Events "Row stored with the submitted values" "SELECT … WHERE event_id=$EA" "QA Test Event|Created by the test suite|2027-03-01|09:30:00|QA Hall|2|open" "$(sql "SELECT CONCAT_WS('|',event_name,description,event_date,event_time,venue,capacity,status) FROM events WHERE event_id=$EA")"
has EV-03 Events "READ: admin list shows the new event" "GET admin/events.php" "QA Test Event" "$(get $A $L/events.php)"
has EV-04 Events "READ: admin event page" "GET admin/event_view.php?id=$EA" "0 of 2 taken" "$(get $A "$L/event_view.php?id=$EA")"
has EV-05 Events "READ: public event page with Register button" "GET event.php?id=$EA" "register.php?event_id=$EA" "$(get $V "$B/event.php?id=$EA")"
has EV-06 Events "READ: public list shows the new event" "GET index.php" "QA Test Event" "$(get $V "$B/index.php")"
T=$(tok $A "admin/event_edit.php?id=$EA")
out=$(get $A "$L/event_edit.php?id=$EA" --data-urlencode "csrf_token=$T" -d "event_name=QA Test Event (edited)&description=&event_date=2027-03-02&event_time=10:00&venue=QA Hall B&capacity=3&status=open" | flash | sed 's/&quot;/"/g')
eq EV-07 Events "UPDATE event (message)" "rename, new date/time/venue, capacity 3, empty description" 'Event "QA Test Event (edited)" was updated.' "$out"
eq EV-08 Events "UPDATE stored; empty description saved as NULL" "SELECT … WHERE event_id=$EA" "QA Test Event (edited)|NULL|2027-03-02|10:00:00|QA Hall B|3" "$(sql "SELECT CONCAT_WS('|',event_name,IFNULL(description,'NULL'),event_date,event_time,venue,capacity) FROM events WHERE event_id=$EA")"
eq EV-09 Events "Edit page for a missing id" "GET admin/event_edit.php?id=99999" "Event not found." "$(get $A "$L/event_edit.php?id=99999" | flash)"
eq EV-10 Events "Non-numeric id" "GET admin/event_view.php?id=abc" "Event not found." "$(get $A "$L/event_view.php?id=abc" | flash)"
eq EV-11 Events "Public page for a missing id" "GET event.php?id=99999" "Sorry, that event could not be found." "$(get $V "$B/event.php?id=99999" | flash)"
# delete is tested in section 15 (cascade) and here on an event without registrations
sql "INSERT INTO events (event_name,event_date,event_time,venue,capacity,status) VALUES ('QA Delete Me','2027-05-05','10:00:00','Room',10,'open')" >/dev/null
ED=$(sql "SELECT event_id FROM events WHERE event_name='QA Delete Me'")
T=$(tok $A admin/events.php)
out=$(get $A $L/event_delete.php --data-urlencode "csrf_token=$T" -d "event_id=$ED" | flash | sed 's/&quot;/"/g')
eq EV-12 Events "DELETE event (message)" "POST event_delete.php event_id=$ED" 'Event "QA Delete Me" was deleted.' "$out"
eq EV-13 Events "DELETE removed the row" "SELECT COUNT(*) … event_id=$ED" "0" "$(sql "SELECT COUNT(*) FROM events WHERE event_id=$ED")"
eq EV-14 Events "Deleting it again" "same POST" "Event not found — it may already have been deleted." "$(get $A $L/event_delete.php --data-urlencode "csrf_token=$T" -d "event_id=$ED" | flash)"
eq EV-15 Events "DELETE via GET is refused" "GET event_delete.php?event_id=1" "That action must be submitted from a form.|1" "$(get $A "$L/event_delete.php?event_id=1" | flash)|$(sql 'SELECT COUNT(*) FROM events WHERE event_id=1')"

# ==================================================================
echo "== 4. Registration CRUD through the website"
reg() { # event_id name email [phone] → final page
    rm -f "$V"; local t; t=$(tok $V "register.php?event_id=$1")
    get $V $B/register.php --data-urlencode "csrf_token=$t" -d "event_id=$1&agree=1" --data-urlencode "full_name=$2" --data-urlencode "email=$3" --data-urlencode "phone=${4:-}"
}
page=$(reg $EA "Alice Tester" "alice@qa.test" "0917 123 4567")
REF=$(sql "SELECT reference_code FROM registrations WHERE email='alice@qa.test'")
has REG-01 Registrations "CREATE registration (INSERT) via public form" "event $EA, Alice Tester, alice@qa.test, 0917 123 4567" "You're registered!" "$page"
eq REG-02 Registrations "Row stored as pending with phone" "SELECT … email='alice@qa.test'" "Alice Tester|0917 123 4567|pending" "$(sql "SELECT CONCAT_WS('|',full_name,phone,status) FROM registrations WHERE email='alice@qa.test'")"
[[ "$REF" =~ ^ERS-[0-9]{4}-[A-HJ-NP-Z2-9]{6}$ ]] && r=ok || r="$REF"
eq REG-03 Registrations "Unique reference code format" "reference_code" "ok" "$r"
has REG-04 Registrations "Confirmation page shows the saved reference" "confirmation.php" "$REF" "$page"
has REG-05 Registrations "Confirmation is printable" "confirmation.php" "window.print()" "$page"
eq REG-06 Registrations "Another visitor cannot see the confirmation" "fresh session GET confirmation.php" "0" "$(curl -s "$B/confirmation.php" | grep -c "$REF")"
RA=$(sql "SELECT registration_id FROM registrations WHERE email='alice@qa.test'")
has REG-07 Registrations "READ: admin list shows it" "GET admin/registrations.php" "$REF" "$(get $A $L/registrations.php)"
has REG-08 Registrations "READ: admin registration page" "GET registration_view.php?id=$RA" "alice@qa.test" "$(get $A "$L/registration_view.php?id=$RA")"
T=$(tok $A "admin/registration_edit.php?id=$RA")
out=$(get $A "$L/registration_edit.php?id=$RA" --data-urlencode "csrf_token=$T" -d "full_name=Alice Q. Tester&email=alice@qa.test&phone=&status=confirmed" | flash)
eq REG-09 Registrations "UPDATE attendee (message)" "name → Alice Q. Tester, phone cleared, status confirmed" "Registration $REF was updated." "$out"
eq REG-10 Registrations "UPDATE stored; empty phone saved as NULL" "SELECT …" "Alice Q. Tester|NULL|confirmed" "$(sql "SELECT CONCAT_WS('|',full_name,IFNULL(phone,'NULL'),status) FROM registrations WHERE registration_id=$RA")"
T=$(tok $A admin/registrations.php)
eq REG-11 Registrations "Quick status: confirmed → pending" "POST registration_status.php" "pending" "$(get $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=$RA&status=pending" >/dev/null; sql "SELECT status FROM registrations WHERE registration_id=$RA")"
eq REG-12 Registrations "Quick status rejects an unknown status" "status=approved" "Invalid status." "$(get $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=$RA&status=approved" | flash)"
eq REG-13 Registrations "Quick status via GET is refused" "GET registration_status.php?…status=cancelled" "pending" "$(go $A "$L/registration_status.php?registration_id=$RA&status=cancelled" >/dev/null; sql "SELECT status FROM registrations WHERE registration_id=$RA")"
reg $EA "Del Me" "delme@qa.test" >/dev/null; RD=$(sql "SELECT registration_id FROM registrations WHERE email='delme@qa.test'")
out=$(get $A $L/registration_delete.php --data-urlencode "csrf_token=$T" -d "registration_id=$RD" | flash)
has REG-14 Registrations "DELETE registration (message)" "POST registration_delete.php id=$RD" "was deleted." "$out"
eq REG-15 Registrations "DELETE removed the row" "SELECT COUNT(*) …" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE registration_id=$RD")"
eq REG-16 Registrations "DELETE via GET is refused" "GET registration_delete.php?registration_id=$RA" "1" "$(go $A "$L/registration_delete.php?registration_id=$RA" >/dev/null; sql "SELECT COUNT(*) FROM registrations WHERE registration_id=$RA")"
eq REG-17 Registrations "View a missing registration" "GET registration_view.php?id=99999" "Registration not found." "$(get $A "$L/registration_view.php?id=99999" | flash)"

# ==================================================================
echo "== 5. Required field validation"
t=$(tok $V register.php)
eq VAL-01 Validation "Registration: everything empty" "event_id='' name='' email='' agree unchecked" "Please choose an event.|Please enter the full name.|Please enter an email address.|Please confirm that your details are correct." "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d 'event_id=&full_name=&email=&phone=' | ferr)"
eq VAL-02 Validation "Registration: name of spaces only" "full_name='    '" "Please enter the full name." "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d "event_id=1&agree=1&email=x@qa.test" --data-urlencode "full_name=    " | ferr)"
long=$(printf 'A%.0s' $(seq 1 101))
eq VAL-03 Validation "Registration: name 101 characters" "full_name=A×101" "Name must be 100 characters or fewer." "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d "event_id=1&agree=1&email=x@qa.test&full_name=$long" | ferr)"
eq VAL-04 Validation "Registration: bad phone" "phone=call-me!" "Phone may contain digits, spaces, +, - and ( ) only (7–20 characters)." "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d "event_id=1&agree=1&email=x@qa.test&full_name=X&phone=call-me!" | ferr)"
eq VAL-05 Validation "Rejected registrations saved nothing" "rows with x@qa.test" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE email='x@qa.test'")"
T=$(tok $A admin/event_add.php)
eq VAL-06 Validation "Event: everything empty / invalid" "name='' date=2027-02-30 time=25:00 venue='' capacity=0 status=bogus" "Event name is required.|Please choose a valid date.|Please choose a valid time.|Capacity must be a whole number from 1 to 5000.|Please choose a valid status.|Venue is required." "$(get $A $L/event_add.php --data-urlencode "csrf_token=$T" -d 'event_name=&event_date=2027-02-30&event_time=25:00&venue=&capacity=0&status=bogus' | ferr)"
k=7
for c in 5001 abc 2.5 -1; do
    eq "VAL-$(printf %02d $k)" Validation "Event capacity '$c' rejected" "capacity=$c" "Capacity must be a whole number from 1 to 5000." "$(get $A $L/event_add.php --data-urlencode "csrf_token=$T" -d "event_name=X&event_date=2027-01-01&event_time=10:00&venue=V&status=open&capacity=$c" | ferr)"
    k=$((k+1))
done
eq VAL-11 Validation "Rejected events saved nothing" "events named X" "0" "$(sql "SELECT COUNT(*) FROM events WHERE event_name='X'")"
T=$(tok $A "admin/registration_edit.php?id=$RA")
eq VAL-12 Validation "Admin registration edit: empty name/email, bad status" "full_name='' email='' status=approved" "Please enter the full name.|Please enter an email address.|Please choose a valid status." "$(get $A "$L/registration_edit.php?id=$RA" --data-urlencode "csrf_token=$T" -d 'full_name=&email=&status=approved' | ferr)"
eq VAL-13 Validation "Form keeps typed values after an error" "name='Keep Me' email=bad" 'value="Keep Me"' "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d 'event_id=1&agree=1&full_name=Keep Me&email=bad' | grep -o 'value="Keep Me"' | head -1)"
n=0; for p in "index.php?q[]=x" "index.php?status[]=open" "admin/events.php?q[]=x" "admin/registrations.php?q[]=x&event_id[]=1"; do
    c=$(get $A "$B/$p" | phperr); eq "VAL-1$((4+n))" Validation "Array instead of text in URL: $p" "$p" "0 PHP errors" "$c PHP errors"; n=$((n+1))
done
t=$(tok $V register.php)
eq VAL-18 Validation "Array instead of text in a form field" "POST register.php full_name[]=x" "0 PHP errors" "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d 'event_id=1&agree=1&full_name[]=x&email[]=y' | phperr) PHP errors"

# ==================================================================
echo "== 6. Invalid email addresses"
n=1
for em in "plainaddress" "a@b" "@qa.test" "a b@qa.test" "a@qa..test" "a@qa.test." "<script>@qa.test" "$(printf 'a%.0s' $(seq 1 141))@qa.test"; do
    label=$em; [ ${#em} -gt 40 ] && label="${#em}-char address"
    eq "EML-0$n" Email "Invalid email rejected: $label" "email=$label" "Please enter a valid email address, e.g. name@example.com." "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d 'event_id=1&agree=1&full_name=Email Test' --data-urlencode "email=$em" | ferr)"
    n=$((n+1))
done
eq EML-09 Email "No invalid email reached the database" "rows named 'Email Test'" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE full_name='Email Test'")"
reg 2 "Mixed Case" "Mixed.Case@QA.Test" >/dev/null
eq EML-10 Email "Valid mixed-case email stored lower-case" "email=Mixed.Case@QA.Test" "mixed.case@qa.test" "$(sql "SELECT email FROM registrations WHERE full_name='Mixed Case'")"
T=$(tok $A "admin/registration_edit.php?id=$RA")
eq EML-11 Email "Admin edit rejects an invalid email" "email=not-an-email" "Please enter a valid email address, e.g. name@example.com." "$(get $A "$L/registration_edit.php?id=$RA" --data-urlencode "csrf_token=$T" -d 'full_name=A&email=not-an-email&status=pending' | ferr)"

# ==================================================================
echo "== 7. Duplicate registrations"
eq DUP-01 Duplicate "Same email, same event" "event $EA, alice@qa.test again" "This email is already registered for this event." "$(reg $EA "Alice Again" "alice@qa.test" | ferr)"
eq DUP-02 Duplicate "Same email, different capitals" "ALICE@QA.TEST" "This email is already registered for this event." "$(reg $EA "Alice Caps" "ALICE@QA.TEST" | ferr)"
eq DUP-03 Duplicate "Only one row for that person" "COUNT event $EA alice@qa.test" "1" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$EA AND email='alice@qa.test'")"
has DUP-04 Duplicate "Same email may register for a different event" "event 2, alice@qa.test" "You're registered!" "$(reg 2 "Alice Tester" "alice@qa.test")"
reg $EA "Bob Tester" "bob@qa.test" >/dev/null; RB=$(sql "SELECT registration_id FROM registrations WHERE event_id=$EA AND email='bob@qa.test'")
T=$(tok $A "admin/registration_edit.php?id=$RB")
eq DUP-05 Duplicate "Admin edit to an email already on that event" "change bob's email to alice@qa.test" "Another registration for this event already uses this email." "$(get $A "$L/registration_edit.php?id=$RB" --data-urlencode "csrf_token=$T" -d 'full_name=Bob&email=alice@qa.test&status=pending' | ferr)"
has DUP-06 Duplicate "Database UNIQUE key blocks a duplicate inserted directly" "INSERT duplicate (event $EA, alice@qa.test) in MySQL" "Duplicate entry" "$(sql "INSERT INTO registrations (event_id,full_name,email,status,reference_code) VALUES ($EA,'Dup','alice@qa.test','pending','ERS-DUP-000001')")"
has DUP-07 Duplicate "Database UNIQUE key blocks a duplicate reference code" "INSERT with existing reference_code $REF" "Duplicate entry" "$(sql "INSERT INTO registrations (event_id,full_name,email,status,reference_code) VALUES (2,'Dup','dupref@qa.test','pending','$REF')")"

# ==================================================================
echo "== 8. Registration when an event is full"
# Event $EA: capacity 3, active = alice(pending) + bob(pending) = 2
reg $EA "Carol Tester" "carol@qa.test" >/dev/null
eq FULL-01 Full "Seats used up to capacity" "capacity 3: alice, bob, carol" "3/3" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM registrations WHERE event_id=$EA AND status<>'cancelled'),'/',capacity) FROM events WHERE event_id=$EA")"
eq FULL-02 Full "Next registration refused" "dave@qa.test on the full event" "Sorry, this event is full." "$(reg $EA "Dave Tester" "dave@qa.test" | ferr)"
eq FULL-03 Full "Refused registration not saved" "COUNT dave" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE email='dave@qa.test'")"
has FULL-04 Full "Public page shows it is full, no Register button" "GET event.php?id=$EA" "Sorry, this event is full." "$(get $V "$B/event.php?id=$EA")"
eq FULL-05 Full "Full event is not offered in the form's dropdown" "GET register.php" "0" "$(curl -s "$B/register.php" | grep -c "value=\"$EA\"")"
T=$(tok $A "admin/event_edit.php?id=$EA")
eq FULL-06 Full "Capacity cannot be set below current registrations" "capacity=2 with 3 active" "Capacity cannot be lower than the 3 active registrations. Cancel some registrations first." "$(get $A "$L/event_edit.php?id=$EA" --data-urlencode "csrf_token=$T" -d 'event_name=QA Test Event (edited)&event_date=2027-03-02&event_time=10:00&venue=QA Hall B&capacity=2&status=open' | ferr)"
T=$(tok $A admin/registrations.php)
get $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=$RB&status=cancelled" >/dev/null
eq FULL-07 Full "Cancelling frees a seat" "bob → cancelled" "2/3" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM registrations WHERE event_id=$EA AND status<>'cancelled'),'/',capacity) FROM events WHERE event_id=$EA")"
has FULL-08 Full "…which a new person can take" "erin@qa.test" "You're registered!" "$(reg $EA "Erin Tester" "erin@qa.test")"
eq FULL-09 Full "Re-activating bob on the full event is refused (quick status)" "bob cancelled → pending" "The event is full (3 of 3 seats taken), so this registration cannot be re-activated." "$(get $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=$RB&status=pending" | flash)"
T=$(tok $A "admin/registration_edit.php?id=$RB")
eq FULL-10 Full "Re-activating bob on the full event is refused (edit form)" "bob cancelled → confirmed" "The event is full (3 of 3 seats taken), so this registration cannot be re-activated." "$(get $A "$L/registration_edit.php?id=$RB" --data-urlencode "csrf_token=$T" -d 'full_name=Bob Tester&email=bob@qa.test&status=confirmed' | ferr)"
eq FULL-11 Full "Bob is still cancelled" "status" "cancelled" "$(sql "SELECT status FROM registrations WHERE registration_id=$RB")"
sql "INSERT INTO events (event_name,event_date,event_time,venue,capacity,status) VALUES ('QA Race','2027-04-01','10:00:00','Lab',3,'open')" >/dev/null
ER=$(sql "SELECT event_id FROM events WHERE event_name='QA Race'")
for i in $(seq 1 12); do ( J="$W/race$i.jar"; rm -f "$J"; tt=$(tok "$J" register.php)
    curl -s -o /dev/null -c "$J" -b "$J" $B/register.php --data-urlencode "csrf_token=$tt" -d "event_id=$ER&agree=1&full_name=Racer $i&email=racer$i@qa.test" ) & done; wait
eq FULL-12 Full "12 simultaneous registrations for 3 seats" "12 parallel POSTs, capacity 3" "3" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$ER")"
# Race between a quick-status re-activation and a new registration (defect check, see report)
sql "INSERT INTO events (event_name,event_date,event_time,venue,capacity,status) VALUES ('QA Race 2','2027-04-02','10:00:00','Lab',1,'open')" >/dev/null
E2=$(sql "SELECT event_id FROM events WHERE event_name='QA Race 2'")
sql "INSERT INTO registrations (event_id,full_name,email,status,reference_code) VALUES ($E2,'Cancelled Carl','carl@qa.test','cancelled','ERS-QA-RACE01')" >/dev/null
RC=$(sql "SELECT registration_id FROM registrations WHERE email='carl@qa.test'")
T=$(tok $A admin/registrations.php)
( "$MYSQL" -uroot -N "$DB" -e "START TRANSACTION; SELECT event_id FROM events WHERE event_id=$E2 FOR UPDATE; SELECT SLEEP(2); INSERT INTO registrations (event_id,full_name,email,status,reference_code) VALUES ($E2,'Seat Taker','taker@qa.test','pending','ERS-QA-RACE02'); COMMIT;" >/dev/null ) &
sleep 0.5
curl -s -o /dev/null -c $A -b $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=$RC&status=pending"
wait
eq FULL-13 Full "Re-activation racing a new registration cannot exceed capacity" "capacity 1; seat taken in a parallel transaction while re-activating" "1" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$E2 AND status<>'cancelled'")"

# ==================================================================
echo "== 9. Registration for cancelled / closed / past events"
eq CAN-01 Cancelled "Cancelled event (id 8) refused" "POST register event_id=8" "This event has been cancelled." "$(reg 8 "Can Test" "can@qa.test" | ferr)"
eq CAN-02 Cancelled "Closed event (id 6) refused" "event_id=6" "Registration for this event is closed." "$(reg 6 "Can Test" "can@qa.test" | ferr)"
eq CAN-03 Cancelled "Completed event (id 7) refused" "event_id=7" "This event has already taken place." "$(reg 7 "Can Test" "can@qa.test" | ferr)"
sql "INSERT INTO events (event_name,event_date,event_time,venue,capacity,status) VALUES ('QA Past Open','2020-01-01','10:00:00','Old Hall',10,'open')" >/dev/null
EP=$(sql "SELECT event_id FROM events WHERE event_name='QA Past Open'")
eq CAN-04 Cancelled "Past event still marked open refused" "event dated 2020-01-01, status open" "This event has already taken place." "$(reg $EP "Can Test" "can@qa.test" | ferr)"
eq CAN-05 Cancelled "Nonexistent event refused" "event_id=99999" "Please choose an event." "$(reg 99999 "Can Test" "can@qa.test" | ferr)"
eq CAN-06 Cancelled "None of the refused attempts saved" "COUNT can@qa.test" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE email='can@qa.test'")"
p=$(get $V "$B/event.php?id=8")
[[ "$p" == *"This event has been cancelled."* && "$p" != *"register.php?event_id=8"* ]] && r=ok || r=missing
eq CAN-07 Cancelled "Cancelled event page: message and no Register link" "GET event.php?id=8" "ok" "$r"
eq CAN-08 Cancelled "Cancelled events hidden from the public list" "GET index.php?status=all" "0" "$(curl -s "$B/index.php?status=all" | grep -c 'CICI Mobile App Design Hackathon')"
T=$(tok $A "admin/event_edit.php?id=2")
get $A "$L/event_edit.php?id=2" --data-urlencode "csrf_token=$T" -d 'event_name=CICI PHP & MySQL Bootcamp&description=x&event_date=2026-12-15&event_time=13:00&venue=CICI Computer Laboratory 2, ISUFST&capacity=30&status=cancelled' >/dev/null
eq CAN-09 Cancelled "Admin cancels an event → new registrations refused" "event 2 set to cancelled, then register" "This event has been cancelled." "$(reg 2 "Late Comer" "late@qa.test" | ferr)"
sql "UPDATE registrations SET status='cancelled' WHERE registration_id=3" >/dev/null
T=$(tok $A admin/registrations.php)
eq CAN-10 Cancelled "Re-activating a registration on a cancelled event refused" "registration 3 (event 2) cancelled → confirmed" "The event is cancelled, so this registration cannot be re-activated." "$(get $A $L/registration_status.php --data-urlencode "csrf_token=$T" -d "registration_id=3&status=confirmed" | flash)"

# ==================================================================
echo "== 10. Search and filtering"
cards() { grep -oE 'h5 card-title mb-0">\s*<a[^>]*>[^<]*' | sed 's/.*>//' | paste -sd'|' -; }
eq SRCH-01 Search "Public: keyword" "q=Excel" "CICI Intro to Data Analytics with Excel" "$(curl -s "$B/index.php?q=Excel" | tr -d '\n' | cards)"
eq SRCH-02 Search "Public: keyword matches venue" "q=Auditorium" "CICI Tech Career Talk: Landing Your First IT Job" "$(curl -s "$B/index.php?q=Auditorium" | tr -d '\n' | cards)"
eq SRCH-03 Search "Public: status=closed" "status=closed" "CICI Leadership Summit for Student Officers" "$(curl -s "$B/index.php?status=closed" | tr -d '\n' | cards)"
eq SRCH-04 Search "Public: keyword + status combined" "q=Leadership&status=open" "" "$(curl -s "$B/index.php?q=Leadership&status=open" | tr -d '\n' | cards)"
has SRCH-05 Search "Public: no match shows empty state" "q=zzzz" "No events match your search" "$(curl -s "$B/index.php?q=zzzz")"
eq SRCH-06 Search "Public: unknown status falls back to open" "status=hacked" "$(curl -s "$B/index.php?status=open" | tr -d '\n' | cards)" "$(curl -s "$B/index.php?status=hacked" | tr -d '\n' | cards)"
eq SRCH-07 Search "Public: % is a literal, not a wildcard" "q=%" "0 events found" "$(curl -s "$B/index.php?q=%25" | grep -oE '[0-9]+ events? found')"
eq SRCH-08 Search "Public: _ is a literal, not a wildcard" "q=_" "0 events found" "$(curl -s "$B/index.php?q=_" | grep -oE '[0-9]+ events? found')"
rows() { grep -oE '<td class="text-secondary">[0-9]+</td>' | wc -l | tr -d ' '; }
eq SRCH-09 Search "Admin events: status=cancelled" "status=cancelled" "2" "$(get $A "$L/events.php?status=cancelled" | rows)"
has SRCH-10 Search "Admin events: keyword" "q=Laboratory" "Showing 1 of" "$(get $A "$L/events.php?q=Laboratory%201")"
regrows() { grep -c 'registration_view.php?id=[0-9]*"><code'; }
eq SRCH-11 Search "Registrations: by reference code" "q=ERS-2026-A1B2C3" "1" "$(get $A "$L/registrations.php?q=ERS-2026-A1B2C3" | regrows)"
eq SRCH-12 Search "Registrations: by email fragment" "q=juan.delacruz" "2" "$(get $A "$L/registrations.php?q=juan.delacruz" | regrows)"
eq SRCH-13 Search "Registrations: by event" "event_id=1" "$(sql 'SELECT COUNT(*) FROM registrations WHERE event_id=1')" "$(get $A "$L/registrations.php?event_id=1" | regrows)"
eq SRCH-14 Search "Registrations: by status" "status=cancelled" "$(sql "SELECT COUNT(*) FROM registrations WHERE status='cancelled'")" "$(get $A "$L/registrations.php?status=cancelled" | regrows)"
eq SRCH-15 Search "Registrations: event + status + keyword" "event_id=$EA status=pending q=qa.test" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$EA AND status='pending' AND email LIKE '%qa.test%'")" "$(get $A "$L/registrations.php?event_id=$EA&status=pending&q=qa.test" | regrows)"
eq SRCH-16 Search "Registrations: status counters match the database" "All/Pending/Confirmed/Cancelled badges" "$(sql "SELECT CONCAT_WS('|',COUNT(*),SUM(status='pending'),SUM(status='confirmed'),SUM(status='cancelled')) FROM registrations")" "$(get $A "$L/registrations.php" | tr -d '\n' | grep -oE 'badge text-bg-light ms-1">[0-9]+' | sed 's/.*>//' | paste -sd'|' -)"

# ==================================================================
echo "== 13. SQL injection and XSS"
tables() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB'"; }
n=1
for q in "' OR 1=1 -- " "%' UNION SELECT password_hash,1,1,1,1,1,1,1,1,1 FROM administrators -- " "'; DROP TABLE events; -- "; do
    pg=$(curl -s "$B/index.php?q=$(python -c 'import sys,urllib.parse;print(urllib.parse.quote(sys.argv[1]))' "$q")")
    c=$(printf '%s' "$pg" | grep -oE '[0-9]+ events? found'); e=$(printf '%s' "$pg" | phperr); h=$(printf '%s' "$pg" | grep -c '\$2y\$')
    eq "SQLI-0$n" SQLi "Public search payload" "q=$q" "0 events found, 0 errors, no hashes" "$c, $e errors, $( [ "$h" = 0 ] && echo no || echo LEAKED) hashes"; n=$((n+1))
done
eq SQLI-04 SQLi "Tables intact after DROP payload" "information_schema" "4" "$(tables)"
eq SQLI-05 SQLi "Numeric id with SQL appended is rejected" "event.php?id=1 OR 1=1" "Sorry, that event could not be found." "$(get "$W/s.jar" "$B/event.php?id=1%20OR%201=1" | flash)"
eq SQLI-06 SQLi "Admin search payloads" "q=' OR 1=1 --  on events + registrations" "0|0" "$(get $A "$L/events.php?q=%27%20OR%201%3D1%20--%20" | rows)|$(get $A "$L/registrations.php?q=%27%20OR%201%3D1%20--%20" | regrows)"
reg 1 "Robert'); DROP TABLE registrations;--" "bobby@qa.test" >/dev/null
eq SQLI-07 SQLi "Injection text in a name is stored as plain text" "full_name=Robert'); DROP TABLE registrations;--" "Robert'); DROP TABLE registrations;--|4" "$(sql "SELECT full_name FROM registrations WHERE email='bobby@qa.test'")|$(tables)"
X='<script>alert("xss")</script>'
T=$(tok $A admin/event_add.php)
get $A $L/event_add.php --data-urlencode "csrf_token=$T" --data-urlencode "event_name=XSS $X" --data-urlencode "description=<img src=x onerror=alert(1)>" --data-urlencode "venue=\"><svg onload=alert(1)>" -d "event_date=2027-06-01&event_time=10:00&capacity=5&status=open" >/dev/null
EX=$(sql "SELECT event_id FROM events WHERE event_name LIKE 'XSS %'")
n=1
for u in "$B/index.php" "$B/event.php?id=$EX" "$L/events.php" "$L/event_view.php?id=$EX" "$L/event_edit.php?id=$EX" "$B/register.php?event_id=$EX"; do
    pg=$(get $A "$u"); raw=$(printf '%s' "$pg" | grep -cE '<script>alert|<img src=x onerror|<svg onload')
    eq "XSS-0$n" XSS "Stored XSS in event fields escaped: ${u#$B/}" "name=$X, description=<img onerror>, venue=\"><svg onload>" "0 raw tags" "$raw raw tags"; n=$((n+1))
done
reg $EX "$X" "xss@qa.test" >/dev/null; RX=$(sql "SELECT registration_id FROM registrations WHERE email='xss@qa.test'")
pg=$(get $A "$L/registrations.php?q=xss@qa.test")$(get $A "$L/registration_view.php?id=$RX")$(get $A "$L/registration_edit.php?id=$RX")
eq XSS-07 XSS "Stored XSS in attendee name escaped (list, view, edit)" "full_name=$X" "0 raw tags" "$(printf '%s' "$pg" | grep -c '<script>alert') raw tags"
has XSS-08 XSS "…shown as text" "same" '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;' "$pg"
eq XSS-09 XSS "Reflected XSS in search box escaped" "index.php?q=\"><script>alert(1)</script>" "0" "$(curl -s "$B/index.php?q=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E" | grep -c '<script>alert(1)')"
t=$(tok $V register.php)
eq XSS-10 XSS "Reflected XSS in a rejected form field escaped" "full_name=<script>alert(1)</script>, email=bad" "0" "$(get $V $B/register.php --data-urlencode "csrf_token=$t" -d 'event_id=1&agree=1&email=bad' --data-urlencode 'full_name=<script>alert(1)</script>' | grep -c '<script>alert(1)')"
LT=$(tok "$W/x.jar" admin/login.php)
eq XSS-11 XSS "Login form escapes the echoed username" "username=\"><script>alert(1)</script>" "0" "$(get "$W/x.jar" $LOGIN --data-urlencode "csrf_token=$LT" --data-urlencode 'username="><script>alert(1)</script>' -d 'password=x' | grep -c '<script>alert(1)')"
sql "DELETE FROM login_attempts" >/dev/null
eq XSS-12 XSS "Delete-confirm text cannot break out of its attribute" "event name with quotes on admin/events.php" "0" "$(get $A "$L/events.php?q=XSS" | grep -c 'data-confirm="Delete "XSS')"

# ==================================================================
echo "== 14. CSRF protection"
csrf() { # id url data check-sql label
    local before after; before=$(sql "$4")
    local m1 m2 m3
    m1=$(get $A "$2" -d "$3" | flash)                                              # no token
    m2=$(get $A "$2" -d "csrf_token=0000000000000000000000000000000000000000000000000000000000000000&$3" | flash)   # wrong token
    local other; other=$(tok "$W/other.jar" index.php)                                # token from another session
    m3=$(get $A "$2" --data-urlencode "csrf_token=$other" -d "$3" | flash)
    after=$(sql "$4")
    [ "$before" == "$after" ] && [[ "$m1$m2$m3" == *"session expired"*"session expired"*"session expired"* ]] && r="refused x3, no change" || r="$m1 / $m2 / $m3 / before=$before after=$after"
    eq "$1" CSRF "$5" "no token / wrong token / other session's token" "refused x3, no change" "$r"
}
csrf CSRF-01 "$L/event_add.php" "event_name=CSRF&event_date=2027-01-01&event_time=10:00&venue=X&capacity=5&status=open" "SELECT COUNT(*) FROM events" "Create event"
csrf CSRF-02 "$L/event_edit.php?id=1" "event_name=CSRF&event_date=2027-01-01&event_time=10:00&venue=CSRF&capacity=50&status=open" "SELECT venue FROM events WHERE event_id=1" "Edit event"
csrf CSRF-03 "$L/event_delete.php" "event_id=1" "SELECT COUNT(*) FROM events" "Delete event"
csrf CSRF-04 "$L/registration_edit.php?id=1" "full_name=CSRF&email=csrf@qa.test&status=cancelled" "SELECT CONCAT(full_name,status) FROM registrations WHERE registration_id=1" "Edit registration"
csrf CSRF-05 "$L/registration_status.php" "registration_id=1&status=cancelled" "SELECT status FROM registrations WHERE registration_id=1" "Change status"
csrf CSRF-06 "$L/registration_delete.php" "registration_id=1" "SELECT COUNT(*) FROM registrations" "Delete registration"
csrf CSRF-07 "$B/register.php" "event_id=1&agree=1&full_name=CSRF&email=csrf@qa.test" "SELECT COUNT(*) FROM registrations" "Public registration"
csrf CSRF-08 "$L/logout.php" "" "SELECT 1" "Logout"
eq CSRF-09 CSRF "…admin still logged in after forged logouts" "GET admin/index.php" "200 " "$(go $A $L/index.php)"
lo=$(get "$W/csrf_login.jar" $LOGIN -d "username=$ADMIN" --data-urlencode "password=$PW" | flash)
eq CSRF-10 CSRF "Login without token refused (even with the right password)" "POST login.php without csrf_token" "Your session expired or the form was invalid. Please try again.|302 $LOGIN" "$lo|$(go "$W/csrf_login.jar" $L/index.php)"
pg=$( (get $A $L/events.php; get $A $L/registrations.php; get $A "$L/event_edit.php?id=1"; get $A "$L/registration_view.php?id=1"; get $A $L/index.php) )
f=$(printf '%s' "$pg" | grep -o '<form method="post"' | wc -l | tr -d ' '); k=$(printf '%s' "$pg" | grep -o 'name="csrf_token"' | wc -l | tr -d ' ')
eq CSRF-11 CSRF "Every POST form on admin pages carries a token" "events, registrations, edit, view, dashboard pages" "$f forms / $f tokens" "$f forms / $k tokens"

echo "== 15. Security headers"
hd=$(curl -s -I -b $A "$L/index.php" | tr -d '\r')
has SEC-01 Headers "Clickjacking protection on admin pages" "GET admin/index.php response headers" "X-Frame-Options: SAMEORIGIN" "$hd"
has SEC-02 Headers "MIME sniffing disabled" "same" "X-Content-Type-Options: nosniff" "$hd"
has SEC-03 Headers "Admin pages not cached" "same" "Cache-Control: no-store" "$hd"

# ==================================================================
echo "== 16. Foreign key integrity"
has FK-01 FK "Registration for a nonexistent event rejected by MySQL" "INSERT … event_id=99999" "foreign key constraint fails" "$(sql "INSERT INTO registrations (event_id,full_name,email,status,reference_code) VALUES (99999,'Orphan','orphan@qa.test','pending','ERS-QA-ORPH1')")"
has FK-02 FK "Invalid status rejected by MySQL (ENUM + CHECK)" "UPDATE registrations SET status='approved'" "ERROR" "$(sql "SET SESSION sql_mode='STRICT_ALL_TABLES'; UPDATE registrations SET status='approved' WHERE registration_id=1")"
has FK-03 FK "Capacity 0 rejected by MySQL CHECK" "UPDATE events SET capacity=0" "chk_events_capacity" "$(sql "UPDATE events SET capacity=0 WHERE event_id=1")"
cnt=$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$EA")
T=$(tok $A admin/events.php)
out=$(get $A $L/event_delete.php --data-urlencode "csrf_token=$T" -d "event_id=$EA" | flash | sed 's/&quot;/"/g')
eq FK-04 FK "Delete event with registrations through the website" "delete event $EA ($cnt registrations)" "Event \"QA Test Event (edited)\" was deleted, together with $cnt registrations." "$out"
eq FK-05 FK "ON DELETE CASCADE removed its registrations" "COUNT registrations event_id=$EA" "0" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$EA")"
eq FK-06 FK "No orphan registrations anywhere" "LEFT JOIN events … WHERE e.event_id IS NULL" "0" "$(sql 'SELECT COUNT(*) FROM registrations r LEFT JOIN events e ON e.event_id=r.event_id WHERE e.event_id IS NULL')"
sql "UPDATE events SET event_id=900 WHERE event_id=$ER" >/dev/null
eq FK-07 FK "ON UPDATE CASCADE follows a changed event_id" "event $ER renumbered to 900" "3|0" "$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=900")|$(sql "SELECT COUNT(*) FROM registrations WHERE event_id=$ER")"
eq FK-08 FK "Foreign key definition" "information_schema.REFERENTIAL_CONSTRAINTS" "fk_registrations_event|events|CASCADE|CASCADE" "$(sql "SELECT CONCAT_WS('|',CONSTRAINT_NAME,REFERENCED_TABLE_NAME,DELETE_RULE,UPDATE_RULE) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$DB'")"

# ==================================================================
echo "== Page health: no PHP errors on any page"
n=1
for u in index.php "index.php?status=all" "event.php?id=1" register.php confirmation.php admin/index.php admin/events.php "admin/event_view.php?id=1" admin/event_add.php "admin/event_edit.php?id=1" admin/registrations.php "admin/registration_view.php?id=1" "admin/registration_edit.php?id=1" test_connection.php; do
    c=$(curl -s -o "$W/p.html" -w '%{http_code}' -c $A -b $A "$B/$u"); e=$(phperr < "$W/p.html")
    [ "$e" != 0 ] && cp "$W/p.html" "$OUT/fail-PAGE-$(printf %02d $n).html"
    eq "PAGE-$(printf %02d $n)" Pages "Loads without PHP errors: $u" "logged-in GET" "200, 0 errors" "$c, $e errors"; n=$((n+1))
done

# (the EXIT trap restores the database)
echo; echo "RESULT: $PASS passed, $FAIL failed  (details: tests/output/results.tsv)"
[ "$FAIL" -eq 0 ]
