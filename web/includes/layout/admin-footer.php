    </main>
  </div>
</div>

<!-- logout sempre por POST + CSRF; os botões da interface usam form="formSairAdmin" -->
<form id="formSairAdmin" method="POST" action="logout.php" class="d-none">
  <?= csrfField() ?>
</form>

<?php include __DIR__ . '/../components/modal-confirmar.php'; ?>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
