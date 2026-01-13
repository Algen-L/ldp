<?php
// Determine the path to root from the current file
$current_page_dir = basename(dirname($_SERVER['PHP_SELF']));
$path_to_root = ($current_page_dir === 'admin') ? '../' : '';
?>
<!-- Base Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/base/reset.css">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/base/variables.css">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/base/global.css">
<!-- Layout Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/sidebar.css">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/header.css">
<link rel="stylesheet" href="<?php echo $path_to_root; ?>css/layout/notifications.css">
<!-- Admin Panel Styles -->
<link rel="stylesheet" href="<?php echo $path_to_root; ?>admin/css/admin.css">