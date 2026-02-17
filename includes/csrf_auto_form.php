<?php
require_once __DIR__ . '/csrf.php';
$csrfToken = csrf_token();
?>
<script>
    (function() {
        const token = <?php echo json_encode($csrfToken); ?>;
        if (!token) return;

        function injectToken(form) {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'GET') return;
            if (form.querySelector('input[name="csrf_token"]')) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = token;
            form.appendChild(input);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(injectToken);
        });
    })();
</script>
