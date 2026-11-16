(() => {
    'use strict';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Helpers: all money is whole cents, like the server ----------
    const divRound = (n, d) => Math.floor((n + Math.floor(d / 2)) / d);

    const fmt = (cents) => {
        const whole = Math.floor(cents / 100);
        const frac = String(cents % 100).padStart(2, '0');
        return whole.toLocaleString('en-US') + '.' + frac;
    };

    const trim = (n) => String(Math.round(n * 100) / 100);

    // A non-negative number from a field, never above max (empty or invalid counts as 0)
    const read = (field, max) => {
        const value = parseFloat(field.value);
        if (!Number.isFinite(value) || value < 0) return 0;
        return Math.min(value, max);
    };

    // ---------- Sections fade up as they scroll into view ----------
    function reveal() {
        if (reduced || !('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.remove('pre');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal').forEach((item) => {
            // Anything already on screen stays visible
            if (item.getBoundingClientRect().top > window.innerHeight * 0.92) {
                item.classList.add('pre');
                observer.observe(item);
            }
        });
    }

    // ---------- Success and info messages fade away ----------
    function messages() {
        document.querySelectorAll('.alert-success, .alert-info').forEach((box) => {
            setTimeout(() => box.remove(), 6000);
        });
    }

    // ---------- Phone menu closes after tapping an in-page link ----------
    function menu() {
        const toggle = document.getElementById('nav-toggle');
        if (!toggle) return;

        document.querySelectorAll('.site-nav a[href^="#"]').forEach((link) => {
            link.addEventListener('click', () => { toggle.checked = false; });
        });
    }

    // ---------- The live payslip calculator ----------
    function calculator() {
        const form = document.getElementById('calc');
        if (!form) return;

        const out = (name) => document.querySelector('[data-out="' + name + '"]');
        const row = (name) => document.querySelector('[data-row="' + name + '"]');

        const rate = form.elements.rate;
        const units = form.elements.units;
        const rateLabel = document.getElementById('calc-rate-label');
        const unitsLabel = document.getElementById('calc-units-label');
        const unitsField = document.getElementById('calc-units-field');
        const netEl = out('net');
        const receipt = document.querySelector('.calc-receipt');

        const types = {
            monthly: { rateLabel: 'Rate per month', unitsLabel: '', unit: '', rate: 3500, units: 0, max: 0 },
            daily:   { rateLabel: 'Rate per day', unitsLabel: 'Days worked', unit: 'days', rate: 80, units: 22, max: 31 },
            hourly:  { rateLabel: 'Rate per hour', unitsLabel: 'Hours worked', unit: 'hours', rate: 12, units: 160, max: 744 },
        };

        // A hidden message for screen readers, so they hear the new net pay
        const status = document.createElement('p');
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        receipt.appendChild(status);
        let statusTimer = 0;

        // Net pay counts up to its new value
        let shown = Number((netEl.textContent || '0').replace(/\D/g, '')) || 0;
        let frame = 0;

        const showNet = (cents) => {
            shown = cents;
            netEl.textContent = fmt(cents);
        };

        const countTo = (target) => {
            cancelAnimationFrame(frame);
            if (reduced || target === shown) {
                showNet(target);
                return;
            }
            const from = shown;
            const start = performance.now();
            const step = (now) => {
                const p = Math.min(1, (now - start) / 450);
                const eased = 1 - Math.pow(1 - p, 3);
                showNet(Math.round(from + (target - from) * eased));
                if (p < 1) frame = requestAnimationFrame(step);
            };
            frame = requestAnimationFrame(step);
        };

        // Same steps and rounding as the real payroll calculation
        function compute() {
            const type = form.elements.type.value;
            const t = types[type];
            const rateValue = read(rate, 1000000);
            const unitsValue = type === 'monthly' ? 0 : read(units, t.max);
            const rateCents = Math.round(rateValue * 100);

            const basic = type === 'monthly'
                ? rateCents
                : divRound(rateCents * Math.round(unitsValue * 100), 100);
            const allowance = form.elements.allowance.checked ? 15000 : 0;
            const gross = basic + allowance;
            const retire = divRound(basic * 500, 10000);   // 5% of basic
            const tax = divRound(gross * 800, 10000);      // 8% of gross
            const beforeAdvance = gross - retire - tax;
            const advance = form.elements.advance.checked ? Math.min(10000, beforeAdvance) : 0;

            return { type, t, rateValue, unitsValue, basic, allowance, gross, retire, tax, advance, net: beforeAdvance - advance };
        }

        function render(animate) {
            const c = compute();

            out('basic-label').textContent = c.type === 'monthly'
                ? 'Monthly salary'
                : trim(c.unitsValue) + ' ' + c.t.unit + ' × ' + fmt(Math.round(c.rateValue * 100));
            out('basic').textContent = fmt(c.basic);
            out('gross').textContent = fmt(c.gross);
            out('retire').textContent = '−' + fmt(c.retire);
            out('tax').textContent = '−' + fmt(c.tax);
            out('advance').textContent = '−' + fmt(c.advance);

            row('allowance').hidden = c.allowance === 0;
            row('advance').hidden = c.advance === 0;

            if (animate) {
                countTo(c.net);
            } else {
                showNet(c.net);
            }

            clearTimeout(statusTimer);
            statusTimer = setTimeout(() => { status.textContent = 'Net pay ' + fmt(c.net); }, 700);
        }

        // Labels, the hours box, and sensible starting values for each pay type
        function applyType(useDefaults) {
            const type = form.elements.type.value;
            const t = types[type];

            rateLabel.textContent = t.rateLabel;
            unitsField.hidden = type === 'monthly';
            if (type !== 'monthly') {
                unitsLabel.textContent = t.unitsLabel;
                units.max = String(t.max);
            }
            if (useDefaults) {
                rate.value = String(t.rate);
                if (type !== 'monthly') units.value = String(t.units);
            }
        }

        // Pull out-of-range numbers back into range once the person leaves the field
        function clampBack() {
            const t = types[form.elements.type.value];
            if (Number(rate.value) > 1000000) rate.value = '1000000';
            if (t.max > 0 && Number(units.value) > t.max) units.value = String(t.max);
            if (Number(rate.value) < 0) rate.value = '0';
            if (Number(units.value) < 0) units.value = '0';
        }

        form.addEventListener('submit', (event) => event.preventDefault());

        form.addEventListener('input', (event) => {
            if (event.target.name === 'type') return;   // handled by the change event
            render(true);
        });

        form.addEventListener('change', (event) => {
            if (event.target.name === 'type') applyType(true);
            clampBack();
            render(true);
        });

        // Match the receipt to whatever the browser restored in the form
        applyType(false);
        render(false);
    }

    function init() {
        reveal();
        messages();
        menu();
        calculator();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
