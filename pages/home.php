<?php
// Logged-in visitors get a button to their own dashboard instead of "Log in"
$visitor = current_user();
$cta     = $visitor
    ? ['Go to your dashboard', home_for_role($visitor['role'])]
    : ['Log in', '/login'];

page_header('Payroll for small businesses');
?>
<section class="landing-hero">
    <div class="landing-copy">
        <p class="eyebrow">Payroll for small businesses</p>
        <h1>Pay your team accurately, without the spreadsheet.</h1>
        <p class="lead">
            Calculate pay, track salary advances and give every worker a clear payslip.
            Built for shops, salons, workshops and small teams.
        </p>
        <div class="form-actions">
            <a class="btn btn-lg" href="<?= e(url($cta[1])) ?>"><?= e($cta[0]) ?></a>
            <a class="btn btn-secondary btn-lg" href="#how">See how it works</a>
        </div>
    </div>

    <aside class="landing-sample card" aria-label="Sample payslip">
        <div class="sample-head">
            <div>
                <strong>Ama Boateng</strong>
                <div class="muted">SP-001 &middot; October 2026</div>
            </div>
            <span class="chip chip-green">Paid</span>
        </div>
        <div class="sample-row"><span>Basic pay</span><span>3,500.00</span></div>
        <div class="sample-row"><span>Transport allowance</span><span>+150.00</span></div>
        <div class="sample-row"><span>Retirement contribution</span><span>−175.00</span></div>
        <div class="sample-row"><span>Income tax</span><span>−292.00</span></div>
        <div class="sample-row"><span>Advance repayment</span><span>−100.00</span></div>
        <div class="sample-net"><span>Net pay</span><span>3,083.00</span></div>
        <p class="hint">Sample payslip</p>
    </aside>
</section>

<section class="landing-section" id="features">
    <h2>Everything a small team needs</h2>
    <div class="feature-grid">
        <div class="card feature">
            <h3>Accurate pay</h3>
            <p>Monthly, daily and hourly pay, with allowances and deductions you can edit yourself.</p>
        </div>
        <div class="card feature">
            <h3>Salary advances</h3>
            <p>Approve advances and recover them automatically over several pay periods.</p>
        </div>
        <div class="card feature">
            <h3>Clear payslips</h3>
            <p>Every worker sees exactly how their pay was calculated, any time they log in.</p>
        </div>
    </div>
</section>

<section class="landing-section" id="how">
    <h2>How it works</h2>
    <ol class="steps">
        <li>
            <span class="step-number" aria-hidden="true">1</span>
            <h3>Add your team</h3>
            <p>Enter each worker with their pay type and rate.</p>
        </li>
        <li>
            <span class="step-number" aria-hidden="true">2</span>
            <h3>Run payroll</h3>
            <p>Review the month, then finalize it with one click.</p>
        </li>
        <li>
            <span class="step-number" aria-hidden="true">3</span>
            <h3>Share payslips</h3>
            <p>Workers log in to view and print their own payslips.</p>
        </li>
    </ol>
</section>
<?php page_footer(); ?>
