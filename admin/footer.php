</div> <!-- End content -->
</div> <!-- End main content area -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Privacy Mode - Hide amounts with asterisks -->
<script src="../includes/privacy_mode.js?v=8"></script>

<!-- Inactivity Timeout - Blur screen after 10 sec, logout after 2 min -->
<script src="../includes/inactivity_timeout.js"></script>
<script src="../includes/navbar_datetime.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
</script>
</body>
</html>




