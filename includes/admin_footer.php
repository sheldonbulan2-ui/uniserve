<?php
/**
 * UniServe Enterprise Admin Layout Footer
 */
if (!defined('UNISERVE_ADMIN_LAYOUT')) {
    exit('Direct access not permitted.');
}
?>
        </div><!-- /.admin-content-body -->

        <!-- GLOBAL ADMIN FOOTER -->
        <footer class="admin-footer">
            <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; flex-wrap: gap: 12px;">
                <div>
                    &copy; <?= date('Y') ?> <strong>UniServe Ecosystem</strong> &bull; Enterprise Governance Console v1.2.0
                </div>
                <div style="display: flex; gap: 16px;">
                    <span>Database: <strong style="color: #60a5fa;">Supabase PostgreSQL</strong></span>
                    <span>&bull;</span>
                    <span>Server: <strong style="color: #34d399;">Render.com Docker</strong></span>
                    <span>&bull;</span>
                    <a href="ping.php" target="_blank" style="color: var(--text-dim); text-decoration: none;">Live Heartbeat ↗</a>
                </div>
            </div>
        </footer>

    </main><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Confirmation Modal Logic -->
<div id="confirmModalBackdrop" class="admin-modal-backdrop">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3 id="confirmModalTitle">Confirm Action</h3>
            <button type="button" class="modal-close-btn" onclick="closeConfirmModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p id="confirmModalMessage" style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5;"></p>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-secondary" onclick="closeConfirmModal()">Cancel</button>
            <a id="confirmModalActionBtn" href="#" class="btn-primary" style="background: #ef4444;">Proceed</a>
        </div>
    </div>
</div>

<script>
function showConfirmModal(title, message, actionUrl, isDanger = true) {
    document.getElementById('confirmModalTitle').innerText = title;
    document.getElementById('confirmModalMessage').innerText = message;
    const btn = document.getElementById('confirmModalActionBtn');
    btn.href = actionUrl;
    btn.innerText = isDanger ? 'Confirm' : 'Proceed';
    btn.style.background = isDanger ? '#ef4444' : 'linear-gradient(135deg, #2563eb, #3b82f6)';
    document.getElementById('confirmModalBackdrop').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModalBackdrop').classList.remove('active');
}

// Auto dismiss flash alerts after 5 seconds
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.admin-alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(a => {
                a.style.transition = 'opacity 0.4s ease';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 400);
            });
        }, 5000);
    }
});
</script>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>
