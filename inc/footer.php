</main>
<!-- /vl-content -->

<!-- ════════════════════════════════════════
     FOOTER
     ════════════════════════════════════════ -->
<footer class="vl-footer">
    <span>&copy; <?= date('Y') ?> Valiantus Associação Veicular</span>
    <div class="vl-footer-links">
        <a href="#">Sobre</a>
        <a href="#">Suporte</a>
        <a href="#">Contato</a>
    </div>
</footer>

<!-- ════════════════════════════════════════
     SCRIPTS
     ════════════════════════════════════════ -->

<!-- Metronic vendors (traz jQuery + Bootstrap 4 JS — ainda necessários para modals e DataTables) -->
<script src="<?= APP_URL ?>/assets/vendors/base/vendors.bundle.js"></script>
<script src="<?= APP_URL ?>/assets/demo/default/base/scripts.bundle.js"></script>

<!-- DataTables JS (depende de jQuery acima) -->
<script src="https://cdn.datatables.net/v/bs4/dt-1.13.8/r-2.5.0/datatables.min.js"></script>

<!-- ════════════════════════════════════════
     Layout JS — topbar dropdowns + sidebar
     ════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    /* ── Sidebar accordion ── */
    window.vlSbToggle = function (btn) {
        var submenu  = btn.nextElementSibling;
        var expanded = btn.getAttribute('aria-expanded') === 'true';

        // fecha outros
        document.querySelectorAll('.vl-sb-link[aria-expanded="true"]').forEach(function (b) {
            if (b !== btn) {
                b.setAttribute('aria-expanded', 'false');
                var s = b.nextElementSibling;
                if (s) s.classList.remove('open');
            }
        });

        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (submenu) submenu.classList.toggle('open', !expanded);
    };

    /* ── Marca link ativo ── */
    (function () {
        var path = window.location.pathname;
        document.querySelectorAll('.vl-sb-sub-link, .vl-sb-link').forEach(function (a) {
            if (a.tagName === 'A' && a.getAttribute('href') && a.getAttribute('href') !== '#') {
                if (path.includes(a.getAttribute('href'))) {
                    a.classList.add('active');
                    var sub = a.closest('.vl-sb-submenu');
                    if (sub) {
                        sub.classList.add('open');
                        var btn = sub.previousElementSibling;
                        if (btn) btn.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        });
    })();

    /* ── Mobile sidebar ── */
    var hamburger = document.getElementById('vlHamburger');
    var sidebar   = document.getElementById('vlSidebar');
    var overlay   = document.getElementById('vlOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        hamburger.classList.add('open');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* ── Topbar dropdowns ── */
    function makeDropdown(btnId, dropId) {
        var btn  = document.getElementById(btnId);
        var drop = document.getElementById(dropId);
        if (!btn || !drop) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = drop.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) drop.classList.add('open');
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.vl-tb-dropdown.open').forEach(function (d) {
            d.classList.remove('open');
        });
    }

    makeDropdown('vlNotifBtn',   'vlNotifDrop');
    makeDropdown('vlProfileBtn', 'vlProfileDrop');

    document.addEventListener('click', closeAllDropdowns);
    document.querySelectorAll('.vl-tb-dropdown').forEach(function (d) {
        d.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    /* ── Logout com SweetAlert (fase de captura para ignorar stopPropagation dos dropdowns) ── */
    document.addEventListener('click', function (e) {
        var anchor = e.target.closest('[data-logout-url]');
        if (!anchor) return;
        e.preventDefault();
        e.stopPropagation();
        var url = anchor.getAttribute('data-logout-url');
        if (!url) return;

        Swal.fire({
            title: 'Deseja realmente sair?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, sair',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3b5bdb'
        }).then(function (r) {
            if (r.isConfirmed) window.location.href = url;
        });
    }, true);

})();
</script>

</body>
</html>
