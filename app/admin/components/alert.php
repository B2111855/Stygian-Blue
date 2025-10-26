<?php if (isset($_GET['msg'])): ?>
    <script>
        alert("<?= htmlspecialchars($_GET['msg']) ?>");
    </script>
<?php endif; ?>
