    </div>
  </div>
</div>
<?php require __DIR__ . '/chat_widget.php'; ?>
<script>
  document.getElementById('themeToggle').addEventListener('click', function () {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
    }
  });
</script>
</body>
</html>
