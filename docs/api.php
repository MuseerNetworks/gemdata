<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

render_header('API Docs');
?>
<div class="space-y-6">
    <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h1 class="text-3xl font-black">GemData Reseller API</h1>
        <p class="mt-3 max-w-3xl text-slate-300">Authenticate every request with <code>X-API-KEY</code> and <code>X-API-SECRET</code>. Use headers as the primary integration path. All endpoints return JSON with <code>status</code>, <code>message</code>, and <code>data</code>.</p>
        <div class="notice notice-success mt-4">
            Production recommendation: keep credentials in headers only, rotate secrets regularly, and treat POST-body credential fallback as a local-compatibility path rather than a preferred integration mode.
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-2xl font-bold">Endpoints</h2>
        <div class="table-shell mt-4">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>POST</td><td>/gemdata/api/buy-airtime.php</td><td>Purchase airtime</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/buy-data.php</td><td>Purchase data</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/pay-electricity.php</td><td>Buy electricity tokens</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/cable-tv.php</td><td>Renew cable TV</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/exam-pin.php</td><td>Buy exam pins</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/recharge-card.php</td><td>Generate recharge cards</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/data-card.php</td><td>Generate data cards</td></tr>
                    <tr><td>POST</td><td>/gemdata/api/bulk-sms.php</td><td>Send bulk SMS</td></tr>
                    <tr><td>GET</td><td>/gemdata/api/balance.php</td><td>Get wallet balance</td></tr>
                    <tr><td>GET</td><td>/gemdata/api/transactions.php</td><td>List transactions</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-2xl font-bold">Sample cURL</h2>
<pre class="mt-4 overflow-auto rounded-xl bg-slate-950 p-4 text-sm text-slate-200"><code>curl -X POST "http://localhost/gemdata/api/buy-airtime.php" \
  -H "X-API-KEY: gk_your_key" \
  -H "X-API-SECRET: gs_your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "08030000000",
    "amount": 1000
  }'</code></pre>
        </article>
        <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-2xl font-bold">Sample JS Fetch</h2>
<pre class="mt-4 overflow-auto rounded-xl bg-slate-950 p-4 text-sm text-slate-200"><code>fetch('http://localhost/gemdata/api/buy-data.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-KEY': 'gk_your_key',
    'X-API-SECRET': 'gs_your_secret'
  },
  body: JSON.stringify({
    phone: '08030000000',
    plan: '2GB',
    amount: 1500
  })
}).then((res) => res.json()).then(console.log);</code></pre>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-2xl font-bold">Authentication Notes</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-300">
                <li>Send <code>X-API-KEY</code> and <code>X-API-SECRET</code> on every request.</li>
                <li>Expect failed requests to return a descriptive <code>message</code> and a non-success <code>status</code>.</li>
                <li>Keep request references on your side for reconciliation and retries.</li>
            </ul>
        </article>
        <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-2xl font-bold">Status Handling</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-300">
                <li><code>success</code>: request completed and provider response is available.</li>
                <li><code>error</code>: validation, auth, or wallet checks failed before completion.</li>
                <li>Store the returned reference and transaction status for later reconciliation.</li>
            </ul>
        </article>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-2xl font-bold">Response Shape</h2>
<pre class="mt-4 overflow-auto rounded-xl bg-slate-950 p-4 text-sm text-slate-200"><code>{
  "status": "success",
  "message": "Transaction successful",
  "data": {
    "reference": "GDT12345",
    "status": "successful"
  }
}</code></pre>
    </section>
</div>
<?php render_footer(); ?>
