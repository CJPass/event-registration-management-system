/*
 * assets/js/script.js
 * ------------------------------------------------------------------
 * Small helpers that run on every page (loaded in includes/footer.php).
 */

// Ask for confirmation before any link or button marked with
// data-confirm="Are you sure?" is followed. Used later for Delete buttons:
//   <a href="event_delete.php?id=3" data-confirm="Delete this event?">Delete</a>
document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-confirm]');
    if (target && !confirm(target.dataset.confirm)) {
        event.preventDefault();
    }
});
