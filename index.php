<?php
/**
 * Fallback entry point.
 *
 * When mod_rewrite is available the .htaccess rules send requests straight to
 * public/index.php and this file is never used. If mod_rewrite is disabled the
 * browser lands here instead and is redirected manually.
 */
header('Location: public/index.php');
exit;
