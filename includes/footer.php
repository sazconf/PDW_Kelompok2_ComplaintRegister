    </main>

    <?php if (!empty($flash_message) && is_array($flash_message)): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-bg-<?php echo htmlspecialchars($flash_message['type']); ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($flash_message['message']); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <footer class="border-top bg-white py-4 mt-auto">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 text-muted small">
            <div class="d-flex align-items-center gap-2">
                <span class="brand-mark brand-mark-sm">
                    <img src="/complaint-system/logo/logo.png" alt="Yogyakarta City Complaint Register logo" class="brand-logo">
                </span>
                <span>&copy; <?php echo date('Y'); ?> Yogyakarta City Complaint Register.</span>
            </div>
            <span>Transparent complaint reporting for public services.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
