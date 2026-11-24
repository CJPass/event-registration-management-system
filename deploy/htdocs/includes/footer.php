<?php
/**
 * includes/footer.php
 * ------------------------------------------------------------------
 * Bottom part of every page: closes <main>, prints the footer and
 * loads the Bootstrap JS bundle (navbar, dropdowns, modals). Include it as the LAST line of each page:
 *     require_once __DIR__ . '/includes/footer.php';
 */
?>
    </div><!-- /.container -->
</main>

<footer class="navbar-brand-bg text-light py-3 mt-auto d-print-none">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <small>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></small>
        <small class="text-secondary text-center text-md-end"><?= e(ORG_NAME) ?> ·
            <a href="<?= e(UNIVERSITY_URL) ?>" class="link-light" target="_blank" rel="noopener">ISUFST</a></small>
    </div>
</footer>

<!-- Bootstrap JS bundle (includes Popper, needed for dropdowns and the mobile menu) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
