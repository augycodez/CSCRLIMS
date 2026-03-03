<?php
/**
 * Global HTML Footer
 */
defined('LAB_APP') or die('Direct access not permitted.');
?>
  </main><!-- /.main-content -->
</div><!-- /.main-wrapper -->

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<!-- Chart.js for Dashboard -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- QRCode library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
// Auto-dismiss flash toast after 4s
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('flashToast');
  if (toast) setTimeout(() => toast.classList.add('hiding'), 4000);
});
</script>
<?php if (isset($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
