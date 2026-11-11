<?php
page_header('Home');
?>
<section class="hero">
    <h1>Payroll made simple for small businesses</h1>
    <p>Calculate pay, track salary advances and give every worker a clear payslip.</p>
    <?php if (env('APP_DEBUG') === '1'): ?>
        <a class="btn" href="<?= e(url('/health')) ?>">System check</a>
    <?php endif; ?>
</section>
<?php page_footer(); ?>