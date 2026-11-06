<SiteLayout title="Sign in" :user="$user" :cart="$cart" :flash="$flash">
<div class="auth-layout">
<div class="auth-intro">
<p class="eyebrow">Your everyday, together</p>
<h1>Welcome<br>back.</h1>
<p class="muted">Your favourites, your cart, your orders.<br>Right where you left them.</p>
<span class="mono small">CRAB / YOUR ACCOUNT</span>
</div>
<Card title="Sign in to Crab">
<p class="muted">Enter your details to continue.</p>
@if($error !== null)<p class="flash" role="alert">{{ $error }}</p>
@endif
<form method="post" action="/login" class="stack">
<Input name="email" label="Email address" type="email" :value="$email" autocomplete="username" :required="true" />
<Input name="password" label="Password" type="password" autocomplete="current-password" :required="true" />
<Button>Sign in →</Button>
</form>
<p class="small muted">New here? <a href="/register">Create an account</a>
</p>
<p class="small muted">Your guest cart will join your account when you sign in.</p>
</Card>
</div>
</SiteLayout>
