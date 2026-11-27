<?php
// ============================================================
// includes/layout/head-assets.php — ícones, fontes e CSS comuns
// ============================================================
// incluído dentro do <head> de todos os layouts (site, painel
// admin e telas de acesso), Bootstrap, ícones e fontes ficam em
// assets/libs/: o site não depende de CDN, funciona em redes
// que bloqueiam domínios externos e até sem internet
?>
  <!-- favicon -->
  <link rel="icon" href="<?= asset('img/favicon.ico') ?>" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('img/favicon-32x32.png') ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('img/apple-touch-icon.png') ?>">
  <meta name="theme-color" content="#1d4052">

  <!-- fontes, Bootstrap e ícones (locais) -->
  <link rel="preload" href="<?= url('assets/libs/fonts/poppins-400-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= asset('libs/fonts/fonts.css') ?>">
  <link rel="stylesheet" href="<?= asset('libs/bootstrap/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('libs/bootstrap-icons/bootstrap-icons.min.css') ?>">

  <!-- CSS próprio (só o que o Bootstrap não cobre), a ordem importa:
       base.css primeiro; o site soma site.css e noticias.css, o painel
       (telas com $css_area = 'admin') soma só admin.css -->
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<?php foreach (($css_area ?? 'site') === 'admin' ? ['admin'] : ['site', 'noticias'] as $cssArquivo): ?>
  <link rel="stylesheet" href="<?= asset('css/' . $cssArquivo . '.css') ?>">
<?php endforeach; ?>
  <script src="<?= asset('js/core/js-ativo.js') ?>"></script>
