<?php
// Determine path to root
$path_to_root = '../';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="theme-color" content="#0f4c75">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Base Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/base/variables.css?v=3.0">

<!-- Layout Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/sidebar.css?v=3.0">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/user.css?v=3.0">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/notifications.css?v=3.0">

<!-- Global Scripts -->
<script src="<?php echo $path_to_root; ?>js/notifications.js"></script>
<script src="<?php echo $path_to_root; ?>js/ui-core.js"></script>

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

<!-- Prevent Sidebar Flash/Animation -->
<script>
    (function () {
        const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (collapsed && window.innerWidth > 992) {
            document.documentElement.classList.add('sidebar-initial-collapsed');
        }
    })();

    // Real-time Clock Functionality handled by ui-core.js
</script>