<?php
// Determine the path to root from the current file
$current_page_dir = basename(dirname($_SERVER['PHP_SELF']));
$path_to_root = ($current_page_dir === 'pages') ? '../' : '';
?>
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Base Styles fallback -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/base/variables.css">

<!-- Notification Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/notifications.css">

<!-- Centralized User Design System -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/user.css">

<!-- Global Notification JS -->
<script src="<?php echo $path_to_root; ?>js/notifications.js"></script>

<?php if (isset($_SESSION['toast'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast(
                "<?php echo htmlspecialchars($_SESSION['toast']['title']); ?>",
                "<?php echo htmlspecialchars($_SESSION['toast']['message']); ?>",
                "<?php echo htmlspecialchars($_SESSION['toast']['type']); ?>"
            );
        });
    </script>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>