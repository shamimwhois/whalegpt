<h2>How a user signs in</h2>
<p>
    The landing page carries an authentication modal. It offers Google and GitHub in one click, and
    an email and password form that works even when no OAuth credentials are configured. A first
    sign-in through a provider creates the account, so the same button both registers and logs in.
</p>

<h2>Provider sign-in</h2>
<p>
    The modal links to a redirect route, which hands off to the provider and returns to a callback:
</p>

<x-docs.code lang="http" label="Routes">GET /auth/{provider}/redirect
GET /auth/{provider}/callback</x-docs.code>

<p>
    <code>{provider}</code> is <code>google</code> or <code>github</code>; any other value is a
    <code>404</code>. The callback links the provider identity to the account, creating the user on
    first sight and matching an existing email when one already exists.
</p>

<h2>Email and password</h2>
<p>The modal's forms post to two endpoints:</p>

<x-docs.code lang="http" label="Routes">POST /auth/login      { email, password, remember }
POST /auth/register   { name, email, password, password_confirmation }</x-docs.code>

<p>
    Registration signs the new account in immediately. Passwords are hashed with the application's
    configured hasher and validated against <code>Password::defaults()</code>.
</p>

<h2>Configuration</h2>
<p>
    Set the provider credentials in your env file. A provider with no client id and secret is shown
    as a disabled button rather than a redirect that cannot complete.
</p>

<x-docs.code lang="env" label=".env">GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
WHALE_REQUIRE_AUTH=false</x-docs.code>

<p>
    <code>WHALE_REQUIRE_AUTH</code> gates the chat interface. Left off, the app is open on its
    machine; turned on, a browser is sent to the sign-in page and a fetch caller receives a
    <code>401</code>.
</p>
