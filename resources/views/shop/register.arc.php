<SiteLayout title="Create an account" :user="$user" :cart="$cart" :flash="$flash">
<div class="auth-layout">
<div class="auth-intro">
<p class="eyebrow">Make yourself at home</p>
<h1>Good things<br>start here.</h1>
<p class="muted">A place for your essentials.<br>An account that keeps them together.</p>
</div>
<Card title="Create your account">
<p class="muted">A few details and you’re in.</p>
<form method="post" action="/register" class="stack">
<Input name="name" label="Full name" autocomplete="name" :required="true" />
<Input name="email" label="Email address" type="email" autocomplete="username" :required="true" />
<Input name="password" label="Password" type="password" autocomplete="new-password" :required="true">
<span class="field-help">At least 8 characters; at most 72 bytes.</span>
</Input>
<Button>Create account →</Button>
</form>
<p class="small muted">Already have an account? <a href="/login">Sign in</a>
</p>
</Card>
</div>
</SiteLayout>
