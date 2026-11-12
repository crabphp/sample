<SiteLayout title="Daily report" :user="$user" :cart="$cart" :flash="$flash">
<AdminNav />
<div class="report-layout">
<Card title="Daily report" eyebrow="Store performance">
<p class="muted">Generate a summary of orders and revenue for a day.</p>
<form method="post" action="/admin/reports" class="stack">
<Input name="day" label="Report date" type="date" :value="$day" required />
<Button>Generate report</Button>
</form>
<form method="get" action="/admin/reports" class="stack">
<Input name="day" id="view-day" label="View an existing report" type="date" :value="$day" required />
<Button variant="secondary">View report</Button>
</form>
</Card>
<Card title="{{ $day }}" eyebrow="Summary">
@if($report === null)<p class="muted">No report for this day yet. Generate one to see the numbers.</p>
@else
<dl class="report-numbers">
@foreach(['orders' => 'Orders', 'paid' => 'Paid', 'shipped' => 'Shipped', 'pending' => 'Pending', 'cancelled' => 'Cancelled'] as $key => $label)<div>
<dt class="muted">{{ $label }}</dt>
<dd class="mono">{{ $report[$key] }}</dd>
</div>
@endforeach
<div>
<dt class="muted">Revenue</dt>
<dd class="mono">${{ number_format($report['revenue'] / 100, 2) }}</dd>
</div>
</dl>
@endif
</Card>
</div>
</SiteLayout>
