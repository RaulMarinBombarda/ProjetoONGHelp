    </main>
  </div>
</div>

<!-- Logout sempre por POST + CSRF; os botões da interface usam form="formSairAdmin". -->
<form id="formSairAdmin" method="POST" action="logout.php" class="d-none">
  <?= csrfField() ?>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui.js?v=3"></script>
<?php
$admin_scripts = $admin_scripts ?? [];
if (!empty($admin_script)) {
    $admin_scripts[] = $admin_script;
}
foreach (array_unique($admin_scripts) as $script):
?>
<script src="../assets/js/<?= htmlspecialchars($script) ?>?v=4"></script>
<?php endforeach; ?>
</body>
</html>
