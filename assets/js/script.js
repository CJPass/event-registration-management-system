/*
 * assets/js/script.js
 * ------------------------------------------------------------------
 * Small extras that run on every page (loaded in includes/footer.php,
 * after Bootstrap's JS bundle). Everything here is optional: if
 * JavaScript is turned off, every page and every CRUD action still works.
 *
 *  1. Print buttons      – any element with data-print opens the print dialog.
 *  2. Tooltips           – any element with data-bs-toggle="tooltip" gets a
 *                          Bootstrap tooltip (shows its title on hover).
 *  3. Auto-hide alerts   – green "success" messages fade out after 5 seconds.
 *  4. Dark mode toggle   – the moon/sun button in the navbar switches the
 *                          theme and remembers the choice in localStorage.
 *                          (The saved theme is applied early in header.php.)
 */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Print buttons
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    // 2. Bootstrap tooltips (Bootstrap needs these to be switched on in JS)
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
    });

    // 3. Auto-hide success alerts; error alerts stay until closed
    document.querySelectorAll('.alert-success.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }, 5000);
    });

    // 4. Dark mode toggle
    var root = document.documentElement;
    function setTheme(theme, save) {
        root.setAttribute('data-bs-theme', theme);
        if (save) {
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            setTheme(root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark', true);
        });
    });
    // Always print in light mode (dark backgrounds waste ink)
    var themeBeforePrint = null;
    window.addEventListener('beforeprint', function () {
        themeBeforePrint = root.getAttribute('data-bs-theme');
        setTheme('light', false);
    });
    window.addEventListener('afterprint', function () {
        if (themeBeforePrint) setTheme(themeBeforePrint, false);
    });
});
