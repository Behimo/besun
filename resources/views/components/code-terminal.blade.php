<div class="code-terminal" data-sr="right" data-sr-delay="120" aria-hidden="true">
    <div class="code-terminal__glow"></div>
    <div class="code-terminal__bar">
        <span class="code-terminal__dot code-terminal__dot--red"></span>
        <span class="code-terminal__dot code-terminal__dot--yellow"></span>
        <span class="code-terminal__dot code-terminal__dot--green"></span>
        <span class="code-terminal__title">rahbar-crm · api/v2/leads</span>
        <span class="code-terminal__status">
            <span class="code-terminal__status-dot"></span>
            live
        </span>
    </div>
    <div class="code-terminal__body">
        <div class="code-terminal__line"><span class="code-terminal__ln">1</span><span class="code-terminal__kw">class</span> <span class="code-terminal__type">LeadPipeline</span> <span class="code-terminal__kw">extends</span> <span class="code-terminal__type">Service</span></div>
        <div class="code-terminal__line"><span class="code-terminal__ln">2</span><span class="code-terminal__kw">public function</span> <span class="code-terminal__fn">syncFromWordPress</span>(<span class="code-terminal__type">LeadDTO</span> $lead)</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">3</span>{</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">4</span>    $customer = <span class="code-terminal__type">Customer</span>::<span class="code-terminal__fn">firstOrCreate</span>([</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">5</span>        <span class="code-terminal__str">'email'</span> => $lead->email,</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">6</span>    ]);</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">7</span>    <span class="code-terminal__kw">return</span> $customer-><span class="code-terminal__fn">assignToSalesTeam</span>()</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">8</span>        -><span class="code-terminal__fn">notify</span>(<span class="code-terminal__str">'لید جدید از سایت'</span>);</div>
        <div class="code-terminal__line"><span class="code-terminal__ln">9</span>}</div>
        <div class="code-terminal__line code-terminal__line--dim"><span class="code-terminal__ln">10</span><span class="code-terminal__comment">// ✓ ۱,۲۴۷ لید امروز همگام شد</span></div>
    </div>
    <div class="code-terminal__footer">
        <span class="code-terminal__metric"><span class="code-terminal__metric-val">12ms</span> avg response</span>
        <span class="code-terminal__metric"><span class="code-terminal__metric-val">99.9%</span> uptime</span>
        <span class="code-terminal__metric"><span class="code-terminal__metric-val">API v2</span> REST</span>
    </div>
</div>
