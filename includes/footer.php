            </div> <!-- End container-fluid -->
        </div> <!-- End #content -->
    </div> <!-- End #wrapper -->

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom jQuery-free Responsive Toggle Script with mobile overlay backdrop support -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            function toggleSidebar(show) {
                if (show) {
                    sidebar.classList.add('show');
                    if (backdrop) backdrop.classList.add('show');
                } else {
                    sidebar.classList.remove('show');
                    if (backdrop) backdrop.classList.remove('show');
                }
            }

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isShown = sidebar.classList.contains('show');
                    toggleSidebar(!isShown);
                });
            }

            // Close sidebar when clicking outside on mobile (clicking the backdrop overlay)
            if (backdrop) {
                backdrop.addEventListener('click', function () {
                    toggleSidebar(false);
                });
            }

            // Close sidebar when clicking outside on mobile or window resize
            document.addEventListener('click', function (e) {
                if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('show')) {
                    const isClickInsideSidebar = sidebar.contains(e.target);
                    const isClickToggleBtn = toggleBtn && toggleBtn.contains(e.target);

                    if (!isClickInsideSidebar && !isClickToggleBtn) {
                        toggleSidebar(false);
                    }
                }
            });

            // Adjust on window resize
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 992) {
                    if (sidebar) sidebar.classList.remove('show');
                    if (backdrop) backdrop.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
