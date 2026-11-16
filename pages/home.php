<?php
// Logged-in visitors get a button to their own dashboard instead of "Log in"
$visitor = current_user();
$cta     = $visitor
    ? ['Go to your dashboard', home_for_role($visitor['role'])]
    : ['Log in', '/login'];

landing_header('Payroll for small businesses');
?>
<section class="hero l-wrap"> 
    <div class="hero-copy">
        <p class="eyebrow">Payroll for small shops, salons and workshops</p>
        <h1>
            Payday,<br>
            <em>itemised.
                <svg class="squiggle" viewBox="0 0 300 18" aria-hidden="true" focusable="false">
                    <path d="M3 11 C 30 2, 55 2, 80 9 S 130 17, 160 9 S 215 2, 240 9 S 280 14, 297 7"/>
                </svg>
            </em>
        </h1>
        <p class="lead">
            SmallPay turns hours, rates and advances into a payslip your whole team can read.
            No spreadsheet, no subscription.
        </p>
        <div class="hero-actions">
            <a class="btn-ink" href="<?= e(url($cta[1])) ?>"><?= e($cta[0]) ?></a>
            <a class="btn-line" href="#try">Try the calculator</a>
        </div>
        <ul class="facts">
            <li>Monthly, daily, hourly</li>
            <li>Advances repaid for you</li>
            <li>Locked once finalized</li>
        </ul>
    </div>

    <div class="hero-art">
        <div class="receipt-back" aria-hidden="true"></div>

        <svg class="badge" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
            <circle class="badge-bg" cx="60" cy="60" r="58"/>
            <defs><path id="badge-path" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"/></defs>
            <g class="spin">
                <text class="badge-ring">
                    <textPath href="#badge-path" textLength="270" lengthAdjust="spacing">NO SPREADSHEET • NO SUBSCRIPTION • </textPath>
                </text>
            </g>
            <text class="badge-s" x="60" y="71" text-anchor="middle">S.</text>
        </svg>

        <div class="receipt float">
            <div class="receipt-paper">
                <div class="r-title">SMALLPAY · PAYSLIP</div>
                <div class="r-sub">Kojo Asante · SP-002 · Oct 2026</div>
                <hr class="r-rule">
                <div class="r-line l1"><span>22 days × 80.00</span><i></i><span>1,760.00</span></div>
                <div class="r-line l2"><span>Transport allowance</span><i></i><span>+150.00</span></div>
                <div class="r-line l3"><span>Retirement</span><i></i><span>−88.00</span></div>
                <div class="r-line l4"><span>Income tax</span><i></i><span>−152.80</span></div>
                <div class="r-line l5"><span>Uniform</span><i></i><span>−20.00</span></div>
                <div class="r-line l6"><span>Advance repayment</span><i></i><span>−100.00</span></div>
                <hr class="r-rule">
                <div class="r-net"><span>NET PAY</span><mark>1,549.20</mark></div>
                <div class="r-note">Sample payslip</div>
            </div>
            <div class="r-edge" aria-hidden="true"></div>
            <div class="stamp" aria-hidden="true">PAID</div>
        </div>
    </div>
</section>

<div class="ticker" aria-hidden="true">
    <div class="ticker-track">
        <span>Monthly pay · Daily pay · Hourly pay · Allowances · Deductions · Salary advances · Locked payslips ·</span>
        <span>Monthly pay · Daily pay · Hourly pay · Allowances · Deductions · Salary advances · Locked payslips ·</span>
    </div>
</div>

<section class="try l-wrap" id="try">
    <div class="section-head reveal">
        <p class="eyebrow">Try it yourself</p>
        <h2>Type a few numbers. <em>Watch the payslip write itself.</em></h2>
        <p class="muted-lg">
            Sample rules only: a transport allowance, a retirement contribution, income tax and an
            advance repayment. In the real app, you set your own.
        </p>
    </div>

    <div class="try-grid">
        <form class="card-paper reveal" id="calc" autocomplete="off" novalidate>
            <fieldset class="field">
                <legend>How is this worker paid?</legend>
                <div class="tabs">
                    <label><input type="radio" name="type" value="monthly"><span>Monthly</span></label>
                    <label><input type="radio" name="type" value="daily" checked><span>Daily</span></label>
                    <label><input type="radio" name="type" value="hourly"><span>Hourly</span></label>
                </div>
            </fieldset>

            <div class="field">
                <label for="calc-rate" id="calc-rate-label">Rate per day</label>
                <input type="number" id="calc-rate" name="rate" value="80" min="0" max="1000000" step="0.01" inputmode="decimal">
            </div>

            <div class="field" id="calc-units-field">
                <label for="calc-units" id="calc-units-label">Days worked</label>
                <input type="number" id="calc-units" name="units" value="22" min="0" max="744" step="0.5" inputmode="decimal">
            </div>

            <label class="check"><input type="checkbox" name="allowance" checked> Transport allowance (+150.00)</label>
            <label class="check"><input type="checkbox" name="advance" checked> Advance repayment (100.00 a month)</label>
        </form>

        <div class="calc-receipt reveal d1">
            <div class="receipt-paper">
                <div class="r-title">SMALLPAY · PAYSLIP</div>
                <div class="r-sub">Your numbers · sample rules</div>
                <hr class="r-rule">
                <div class="r-line"><span data-out="basic-label">22 days × 80.00</span><i></i><span data-out="basic">1,760.00</span></div>
                <div class="r-line" data-row="allowance"><span>Transport allowance</span><i></i><span>+150.00</span></div>
                <div class="r-line"><span>Retirement (5% of basic)</span><i></i><span data-out="retire">−88.00</span></div>
                <div class="r-line"><span>Income tax (8% of gross)</span><i></i><span data-out="tax">−152.80</span></div>
                <div class="r-line" data-row="advance"><span>Advance repayment</span><i></i><span data-out="advance">−100.00</span></div>
                <hr class="r-rule">
                <div class="r-gross"><span>Gross pay</span><span data-out="gross">1,910.00</span></div>
                <div class="r-net"><span>NET PAY</span><mark data-out="net">1,569.20</mark></div>
            </div>
            <div class="r-edge" aria-hidden="true"></div>
        </div>
    </div>
</section>

<section class="flow l-wrap" id="flow">
    <div class="section-head reveal">
        <p class="eyebrow">How a payslip is made</p>
        <h2>Watch 1,910.00 turn into <em>1,549.20.</em></h2>
        <p class="muted-lg">
            A daily-paid worker: 22 days at 80.00, one allowance, three deductions and an advance
            repayment. Every part is a line on the payslip.
        </p>
    </div>

    <div class="bar-wrap reveal">
        <div class="bar-label">GROSS PAY 1,910.00</div>
        <div class="bar-track">
            <div class="bar seg-net">Net pay</div>
            <div class="bar seg-ded"></div>
            <div class="bar seg-adv"></div>
        </div>
        <div class="legend">
            <div class="key"><i class="k-net"></i><span><b>Net pay</b> 1,549.20 (81%)</span></div>
            <div class="key"><i class="k-ded"></i><span><b>Deductions</b> 260.80 (14%)</span></div>
            <div class="key"><i class="k-adv"></i><span><b>Advance repayment</b> 100.00 (5%)</span></div>
        </div>
        <p class="bar-note">Sample figures. Percentages are rounded.</p>
    </div>
</section>

<section class="split l-wrap">
    <div class="panel panel-ink reveal">
        <p class="eyebrow">For the owner</p>
        <h3>Set the rules once. Run the month in minutes.</h3>
        <ul class="ticks">
            <li>Apply allowances and deductions to everyone, or to a few</li>
            <li>Approve advances and let them repay themselves</li>
            <li>Finalize to lock the month for good</li>
        </ul>
    </div>
    <div class="panel panel-line reveal d1">
        <p class="eyebrow">For the worker</p>
        <h3>Know exactly how your pay was worked out.</h3>
        <ul class="ticks">
            <li>See every line of your payslip, any time</li>
            <li>Ask for an advance from your phone</li>
            <li>Print or save your payslip</li>
        </ul>
    </div>
</section>

<section class="cta">
    <div class="l-wrap cta-inner">
        <h2>Ready for payday?</h2>
        <a class="btn-ink" href="<?= e(url($cta[1])) ?>"><?= e($cta[0]) ?></a>
    </div>
</section>
<?php landing_footer(); ?> 
