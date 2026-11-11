<?php
page_header('Page not found');
?>
<h1>404: Page not found</h1>
<p>The page you asked for does not exist.</p>
<p><a href="<?= e(url('/')) ?>">Back to home</a></p>
<?php page_footer(); ?>