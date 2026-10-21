

# 1. Purpose

Convert the existing Admin Login UI into a real, secure authentication system.

This specification establishes the authentication and session-security foundation that all future administrative modules will use.

After implementation:

* unauthenticated users must not access protected admin pages;
* valid administrators can securely log in;
* invalid credentials are rejected;
* authenticated sessions are securely maintained;
* administrators can log out;
* inactive sessions expire;
* authentication attempts are protected against brute-force abuse;
* CSRF protection is established;
* admin account status is respected;
* security-relevant events are logged;
* future admin modules can use the same authentication/authorization foundation.

This is a security-critical specification.

Do not simplify or bypass security controls for convenience.

---

# 2. Required Reading

Before changing anything, read:

```text
CLAUDE.md
PROGRESS.md

docs/01-project-context.md
docs/02-scope-and-business-rules.md
docs/03-architecture-and-data.md
docs/04-design-and-ux-rules.md
docs/05-security-and-integrations.md
docs/06-ai-development-and-testing.md
```

Also read:

```text
specs/
```

and inspect the current implementation of:

```text
admin/
app/
includes/
assets/
public/
```

Inspect the existing:

* Admin Login UI
* Admin Dashboard
* database configuration
* environment configuration
* database schema
* admin_users table
* audit/security log table
* existing helpers/services

Do not assume names or structures exist.

---

# 3. Authority

Follow this hierarchy:

1. Approved business requirements
2. Business rules
3. This specification
4. Security rules
5. Architecture rules
6. Design rules
7. Technical convenience

Security requirements cannot be weakened merely because an implementation is easier.

If an existing implementation conflicts with this specification, identify the conflict and resolve it according to the approved architecture.

---

# 4. Scope

## 4.1 In Scope

Implement:

* Admin login
* Admin logout
* Authentication service
* Authentication/session guard
* Password verification
* Secure password hashing
* Admin account status checking
* Session management
* Session timeout
* Secure session cookies
* Session ID regeneration
* CSRF protection
* Login brute-force/rate-limit protection
* Generic login errors
* Authentication event logging
* Admin authorization foundation
* Protected admin routes
* Initial administrator creation mechanism
* Password reset foundation where appropriate
* Security-focused testing
* Integration with the existing Admin UI

---

# 5. Out of Scope

Do not implement:

* Vehicle management
* Vehicle image management
* Brand management
* Shipment management
* Enquiry management
* Team management
* Content management
* Settings management
* Customer accounts
* Customer authentication
* Customer registration
* Customer passwords
* Social login
* Google login
* Facebook login
* Two-factor authentication unless separately approved
* Passkeys/WebAuthn unless separately approved
* Brevo transactional email implementation
* Password reset email delivery through Brevo
* Public API authentication
* JWT authentication
* OAuth
* Remember-me persistent authentication unless separately approved

Do not modify unrelated functionality.

---

# 6. Authentication Model

The application uses traditional server-side session authentication.

Do NOT introduce JWTs.

Do NOT introduce OAuth.

Do NOT introduce a separate authentication server.

The expected flow is:

```text
Browser
   ↓
Admin Login
   ↓
CSRF validation
   ↓
Rate-limit check
   ↓
Validate credentials
   ↓
Find admin account
   ↓
Check account status
   ↓
Verify password
   ↓
Regenerate session ID
   ↓
Create authenticated session
   ↓
Protected Admin Area
```

---

# 7. Admin User Model

Use the existing `admin_users` table created by Specification 02.

The application must use the existing schema rather than creating a competing user table.

Expected relevant fields include:

```text
id
name
email
password_hash
role
status
last_login_at
created_at
updated_at
```

Inspect the actual schema before implementation.

Do not modify the schema unless required by this specification and justified by the existing architecture.

---

# 8. Password Security

Passwords must never be stored in plaintext.

Use PHP's password hashing API:

```php
password_hash()
```

and verify using:

```php
password_verify()
```

Prefer PHP's default secure password algorithm unless the project explicitly requires another supported algorithm.

Do not implement your own hashing algorithm.

Do not use:

```text
MD5
SHA1
SHA256(password)
```

as password storage mechanisms.

Do not encrypt passwords instead of hashing them.

---

# 9. Password Requirements

The administrator password must meet a reasonable security policy.

At minimum:

* minimum length: 12 characters
* allow uppercase characters
* allow lowercase characters
* allow numbers
* allow symbols

Do not create an unnecessarily restrictive rule that prevents strong passphrases.

The password strength indicator on the existing login/setup UI should provide useful feedback without exposing the password.

Never log passwords.

Never store passwords in session variables.

Never include passwords in error messages.

---

# 10. Initial Administrator Creation

Do NOT create a hardcoded administrator account.

Do NOT seed:

```text
admin@example.com
Admin123
```

or any similar default credentials.

The first administrator must be created through a secure local/controlled setup mechanism.

Prefer a CLI command or setup script that:

1. accepts administrator name;
2. accepts administrator email;
3. securely accepts a password;
4. validates the password;
5. hashes the password;
6. creates the admin record;
7. prevents accidental duplicate administrator creation where appropriate.

The setup mechanism must not display or log the password.

If a CLI implementation is used, the password should be entered securely rather than exposed in the command history.

Do not expose the setup mechanism publicly through the production website.

---

# 11. Email Validation

Admin email addresses must be validated server-side.

Use appropriate PHP validation mechanisms.

Email addresses should be normalized consistently before lookup, according to the project's chosen convention.

The database's unique constraint remains the final protection against duplicate accounts.

---

# 12. Account Status

Authentication must respect the admin user's status.

If the account is inactive/disabled according to the approved schema:

* reject login;
* use a generic user-facing authentication response;
* log the security event;
* do not reveal unnecessary account information.

Do not allow a disabled administrator to authenticate simply because the password is correct.

---

# 13. Generic Login Errors

Do not reveal whether the submitted email exists.

Avoid messages such as:

```text
Email address does not exist.
```

or:

```text
Incorrect password for this account.
```

Use a generic response such as:

```text
Invalid email or password.
```

This reduces account enumeration risk.

---

# 14. Login Rate Limiting

Protect the login endpoint against brute-force attacks.

The system should track repeated failed authentication attempts.

At minimum, use a combination of:

* IP-based throttling;
* account/email-based throttling;
* progressive delay and/or temporary lockout.

Do not permanently lock an administrator account merely because of ordinary failed login attempts.

The implementation should avoid creating an easy denial-of-service mechanism against legitimate administrators.

Use the existing security/audit logging infrastructure where appropriate.

---

# 15. Rate-Limit Behavior

The exact thresholds should be implemented according to a reasonable secure design.

For example, the system may:

* allow a small number of failures;
* progressively delay subsequent attempts;
* temporarily reject excessive attempts;
* reset the failure state after successful authentication or a suitable period.

Do not make the login endpoint infinitely guessable.

Do not expose internal rate-limit counters to the browser.

Do not reveal whether the rate limit was triggered because of a specific account.

---

# 16. Session Security

Use PHP server-side sessions.

Before starting sessions, configure secure session behavior.

Where supported:

```text
HttpOnly = true
Secure = true in HTTPS production
SameSite = Lax or stricter where compatible
```

The application must not store authentication credentials in client-side storage.

Do not store authentication state in:

```text
localStorage
sessionStorage
cookies containing plaintext credentials
```

Authentication state should be represented by the secure server-side session.

---

# 17. Session ID Regeneration

After successful authentication:

```php
session_regenerate_id(true);
```

must be used or an equivalent secure mechanism.

This prevents session fixation.

Do not regenerate the session only visually or through JavaScript.

It must occur server-side after successful authentication.

---

# 18. Session Contents

Keep session contents minimal.

Store only what is required to identify the authenticated administrator and their authorization context.

For example:

```text
authenticated user ID
role
authentication timestamp
last activity timestamp
```

Do not store:

* password
* password hash
* database password
* Brevo API key
* other secrets

---

# 19. Session Timeout

Implement inactivity timeout.

A reasonable initial value is:

```text
30 minutes
```

of inactivity.

The timeout should be configurable through application configuration rather than scattered hardcoded values.

When the session expires:

* destroy the session;
* require login again;
* redirect to the login page;
* display a clear but non-sensitive message.

Do not automatically extend an expired session without authentication.

---

# 20. Logout

Implement a real server-side logout.

Logout should:

1. clear authentication state;
2. clear session data;
3. destroy the session;
4. invalidate the session cookie where appropriate;
5. redirect to the login page.

Do not implement logout as a JavaScript-only action.

Logout must work even if JavaScript is disabled.

---

# 21. Protected Admin Routes

Create a reusable authentication guard/middleware.

Potential location:

```text
app/helpers/
```

or:

```text
app/services/
```

depending on the existing architecture.

Do not duplicate authentication checks throughout every page.

The guard should provide a reusable mechanism such as:

```text
requireAdmin()
```

or an equivalent architecture-compatible implementation.

Protected admin pages must call the guard before rendering protected content.

---

# 22. Authentication Guard Behavior

If a user is not authenticated:

```text
→ do not render protected admin content
→ redirect to /admin/login
```

If a session exists but is invalid/expired:

```text
→ destroy invalid session
→ redirect to login
```

Do not allow direct URL access to bypass authentication.

For example, an unauthenticated request to:

```text
/admin/
```

must not reveal dashboard information.

The same protection must eventually apply to:

```text
/admin/vehicles/
 /admin/shipments/
 /admin/enquiries/
 /admin/team/
 /admin/brands/
 /admin/content/
 /admin/settings/
```

Even though these modules are not yet implemented.

---

# 23. Authorization Foundation

Authentication answers:

> Who is this administrator?

Authorization answers:

> What is this administrator allowed to do?

Establish the foundation now.

Use the existing `role` field.

Do not build a complex permission system unless required.

At minimum, support the concept of:

```text
administrator
```

and preserve the architecture so more granular roles/permissions can be introduced later.

Do not give every future role unrestricted access by default without an explicit rule.

---

# 24. CSRF Protection

Implement server-side CSRF protection for state-changing authentication operations.

At minimum:

* login form
* logout action where applicable
* password-related state-changing forms

Generate a cryptographically secure CSRF token.

Use PHP's secure random mechanisms.

Validate the token server-side.

Use constant-time comparison where appropriate.

Do not rely on JavaScript-only CSRF protection.

Do not place CSRF secrets in publicly accessible JavaScript.

---

# 25. Login Form

Use the existing Admin Login UI.

Do not redesign the page unless required to support functionality.

The form should contain:

```text
Email
Password
Show/Hide Password
Sign In
```

The existing design may include:

```text
Forgot Password?
```

If password reset is not yet implemented, do not make it falsely functional.

Do not create fake password reset functionality.

---

# 26. Login Validation

Server-side validation must occur regardless of client-side validation.

Validate:

* CSRF token
* email format
* password presence
* request method
* rate-limit state

Do not trust browser validation.

---

# 27. Authentication Flow

The implementation should follow:

```text
POST /admin/login
        ↓
Verify request method
        ↓
Verify CSRF token
        ↓
Check rate limit
        ↓
Validate email/password
        ↓
Find admin by email
        ↓
Check account status
        ↓
Verify password
        ↓
If failed:
    record security event
    apply throttling
    return generic error

If successful:
    reset failure state
    update last_login_at
    regenerate session ID
    create authenticated session
    redirect to admin dashboard
```

Do not perform unnecessary database queries.

Use prepared statements.

---

# 28. Last Login

On successful authentication, update:

```text
last_login_at
```

for the administrator.

Do not update it on failed login.

Do not expose unnecessary login history on the dashboard yet.

---

# 29. Audit and Security Logging

Use the existing audit/security logging table from Specification 02.

Record appropriate security events such as:

```text
LOGIN_SUCCESS
LOGIN_FAILURE
LOGOUT
SESSION_TIMEOUT
ACCOUNT_DISABLED_LOGIN_ATTEMPT
RATE_LIMIT_TRIGGERED
```

Use the actual logging architecture established in the project.

Log useful metadata where appropriate, such as:

* admin user ID when known
* event type
* timestamp
* IP address where appropriate
* user agent where appropriate
* request/context information that is safe to retain

Never log:

* passwords
* password hashes
* session IDs
* CSRF tokens
* API keys
* database credentials
* other secrets

---

# 30. Login Failure Logging

A failed login where the account cannot be identified may still be logged.

Do not store the submitted password.

Do not store sensitive authentication material.

Be mindful of privacy when storing IP addresses and user-agent information.

---

# 31. Session Cookie Security

Configure cookies appropriately.

Production should use HTTPS.

When HTTPS is active:

```text
Secure = true
HttpOnly = true
SameSite = Lax
```

or a stricter compatible policy.

Do not force `Secure=true` in a way that makes the local HTTP development environment unusable unless the configuration correctly distinguishes development and production.

Use environment-aware configuration.

---

# 32. Environment Separation

Development and production behavior must remain separate.

Development may use:

```text
http://localhost
```

Production must use HTTPS.

Do not hardcode production credentials.

Do not commit:

```text
.env
```

or other secret files.

The existing `.gitignore` must continue protecting secrets.

---

# 33. Error Handling

Production must never expose:

* PHP stack traces
* SQL queries
* filesystem paths
* database credentials
* environment variables
* internal class names unnecessarily
* session information

User-facing login errors must remain generic.

Technical details should go to the appropriate development log.

---

# 34. Security Headers

Where appropriate for the existing application, establish foundational security headers.

Evaluate:

```text
Content-Security-Policy
X-Content-Type-Options
Referrer-Policy
X-Frame-Options
```

Do not introduce a CSP that breaks the existing approved UI.

Document any headers that are intentionally deferred.

Security headers should be implemented centrally rather than duplicated across pages where the architecture allows.

---

# 35. Clickjacking Protection

Admin pages must not be freely embedded in external frames.

Use an appropriate mechanism such as:

```text
X-Frame-Options
```

and/or CSP `frame-ancestors`.

---

# 36. Cache Control

Authenticated admin pages should not be cached in ways that could expose sensitive information after logout.

Where appropriate, use secure cache-control headers.

After logout, using the browser Back button should not expose a usable authenticated page.

---

# 37. Direct URL Testing

Test direct access to protected pages.

Example:

```text
/admin/
/admin/index.php
```

An unauthenticated browser must be redirected to login.

Do not rely on users clicking through the UI.

Security must exist at the server-side route level.

---

# 38. Database Security

Use PDO prepared statements.

Never construct authentication queries using string concatenation.

Example conceptually:

```sql
SELECT ...
FROM admin_users
WHERE email = :email
LIMIT 1
```

Bind parameters through PDO.

Do not expose database errors to the browser.

---

# 39. Account Enumeration Protection

The following should produce equivalent user-facing behavior:

```text
Unknown email
Known email + wrong password
Disabled account
```

Do not tell the attacker which condition occurred.

Internal logs may distinguish these events for legitimate administration/security analysis.

---

# 40. Timing Considerations

Avoid unnecessarily obvious timing differences between nonexistent users and incorrect passwords.

Where appropriate, perform password verification using a safe dummy hash when no matching account exists.

Do not invent insecure shortcuts such as:

```php
if (!$user) return;
```

without considering enumeration/timing implications.

Keep implementation practical for PHP 8.x.

---

# 41. Password Reset Foundation

Do not implement full Brevo password-reset email delivery in this specification.

However, inspect the architecture and ensure the authentication system can support secure password reset later.

Do not create insecure reset links such as:

```text
/admin/reset-password.php?user_id=5
```

A future reset system must use:

* cryptographically secure random tokens
* expiration
* single use
* secure storage
* no plaintext password storage

Do not implement the full feature unless required by the current existing UI/architecture.

---

# 42. Dependencies

Do not add an authentication framework.

Use native PHP security capabilities wherever practical.

Do not introduce:

```text
Laravel
Symfony
JWT libraries
OAuth libraries
third-party authentication frameworks
```

unless explicitly approved.

Use existing project dependencies only.

---

# 43. Existing Admin UI

Preserve the current Admin Login and Dashboard UI.

Only connect the UI to the real authentication system.

After successful login:

```text
/admin/login
        ↓
/admin/
```

or the project's established dashboard route.

After logout:

```text
/admin/logout
        ↓
/admin/login
```

Do not redesign the admin dashboard.

---

# 44. Authentication State in UI

When authenticated, the admin interface may display the authenticated administrator's name if it is available from the session/database.

Do not display:

* password
* email unnecessarily
* sensitive account data

If the current header/profile UI has a placeholder admin name, replace it with the real authenticated administrator name once available.

Do not create fake administrator information.

---

# 45. Files and Components

Inspect existing files first.

Potential components may include:

```text
app/services/AuthService.php
app/services/SessionService.php
app/helpers/auth.php
app/helpers/csrf.php
app/helpers/security.php
admin/login.php
admin/logout.php
admin/index.php
```

These are examples, not mandatory filenames.

Follow the existing architecture.

Do not create duplicate authentication systems.

---

# 46. Database Changes

Prefer using the existing database schema.

Only make schema changes if genuinely required.

If a schema change is necessary:

* explain why;
* use a reproducible SQL change;
* preserve existing data;
* add appropriate indexes/constraints;
* test the change.

Do not add unnecessary tables.

---

# 47. Local Development

The authentication system must work with the current local PHP development setup.

The project is currently being developed locally using PHP 8.4.x and MySQL 8.4.x.

Do not introduce requirements that cannot run in the current environment without approval.

---

# 48. Testing

Testing is mandatory.

## 48.1 Successful Login

Create a legitimate local administrator using the approved setup mechanism.

Verify:

* correct email/password succeeds;
* session is created;
* session ID changes after authentication;
* dashboard loads;
* administrator name is correctly available if displayed;
* `last_login_at` updates.

---

## 48.2 Invalid Password

Attempt login with:

```text
correct email
incorrect password
```

Verify:

* login fails;
* generic error is shown;
* no sensitive information is revealed;
* failure is logged;
* throttling behavior works.

---

## 48.3 Unknown Email

Attempt login with a nonexistent email.

Verify:

* same generic error;
* no account enumeration;
* failure is logged appropriately;
* no sensitive information is revealed.

---

## 48.4 Disabled Account

Create/use a local disabled test account.

Verify:

* login fails;
* generic response is shown;
* security event is logged;
* no authenticated session is created.

---

## 48.5 CSRF

Submit login without a valid CSRF token.

Verify:

* request is rejected;
* no login occurs;
* appropriate security event is logged where applicable.

---

## 48.6 Session Expiration

Use a development-configurable short timeout to test.

Verify:

* session expires;
* protected page redirects to login;
* session is destroyed;
* user must authenticate again.

Do not permanently set the production timeout to the short testing value.

---

## 48.7 Logout

Verify:

* logout works;
* session is destroyed;
* authenticated state disappears;
* dashboard cannot be accessed afterward;
* browser Back does not expose a usable authenticated page.

---

## 48.8 Direct Access

Open protected admin URLs in a new/incognito browser without authentication.

Verify:

```text
/admin/
/admin/index.php
```

cannot reveal dashboard information.

---

## 48.9 Rate Limiting

Perform repeated failed logins.

Verify:

* throttling occurs;
* legitimate account is not permanently destroyed;
* generic user-facing response remains;
* no credentials or internal counters are exposed.

---

## 48.10 Session Fixation

Verify that the session ID changes after successful authentication.

---

## 48.11 Browser Testing

Check:

* Chrome
* mobile viewport
* desktop viewport

Verify:

* login UI remains responsive;
* errors display cleanly;
* show/hide password works;
* no console errors;
* no broken assets.

---

# 49. Security Review Checklist

Before completion, verify:

```text
[ ] Passwords hashed
[ ] Passwords never logged
[ ] Password verification secure
[ ] CSRF protection implemented
[ ] Session ID regenerated after login
[ ] Secure session cookies configured
[ ] HttpOnly enabled
[ ] SameSite configured
[ ] HTTPS-aware Secure cookie behavior
[ ] Session timeout implemented
[ ] Logout destroys session
[ ] Direct admin access protected
[ ] Generic login errors
[ ] Account enumeration mitigated
[ ] Login rate limiting implemented
[ ] Disabled accounts rejected
[ ] PDO prepared statements used
[ ] No credentials committed
[ ] No secrets in frontend
[ ] Security events logged
[ ] No sensitive authentication material logged
[ ] Production errors hidden
[ ] Admin authorization foundation established
```

---

# 50. Acceptance Criteria

This specification is complete only when:

* [ ] Real admin login works.
* [ ] Passwords are securely hashed.
* [ ] Password verification works.
* [ ] No default hardcoded admin credentials exist.
* [ ] Initial admin can be created securely.
* [ ] Invalid credentials are rejected.
* [ ] Account status is respected.
* [ ] Generic authentication errors are used.
* [ ] CSRF protection works.
* [ ] Session ID regenerates after login.
* [ ] Session cookies are appropriately secured.
* [ ] Session timeout works.
* [ ] Logout completely destroys authentication state.
* [ ] Protected admin routes reject unauthenticated users.
* [ ] Authentication guard is reusable by future modules.
* [ ] Authorization foundation exists.
* [ ] Login rate limiting works.
* [ ] Authentication events are logged.
* [ ] Sensitive information is never logged.
* [ ] No database credentials are exposed.
* [ ] No passwords are exposed.
* [ ] Existing Admin UI remains intact.
* [ ] Dashboard still works after authentication is introduced.
* [ ] No unrelated admin modules were implemented.
* [ ] No unnecessary dependencies were added.
* [ ] Security tests pass.
* [ ] Responsive login behavior passes.
* [ ] Browser console has no new errors.

---

# 51. PROGRESS.md

Only after implementation and testing genuinely pass, update:

```text
Spec 03B — Admin Authentication & Security Foundation
Status: IMPLEMENTED
```

Record:

* implementation date
* files changed
* database changes
* security controls implemented
* tests performed
* known issues

Then set the next task to:

```text
Spec 05 — Brand Management
```

Do not mark this specification complete merely because login appears to work.

---

# 52. Completion Report

After implementation, provide:

```text
IMPLEMENTED
- ...

AUTHENTICATION
- ...

SESSION SECURITY
- ...

CSRF
- ...

RATE LIMITING
- ...

AUTHORIZATION
- ...

AUDIT LOGGING
- ...

FILES CHANGED
- ...

DATABASE CHANGES
- ...

SECURITY TESTING
- ...

BROWSER TESTING
- ...

RESPONSIVE TESTING
- ...

KNOWN ISSUES
- ...

OUT OF SCOPE
- ...

PROGRESS UPDATED
- Yes/No
```

Do not claim tests were performed if they were not actually performed.

Do not claim security controls were implemented if they were only partially implemented.

---

# 53. STOP CONDITION

STOP after completing this specification.

Do not automatically implement:

* Brands
* Vehicles
* Images
* Shipments
* Tracking
* Enquiries
* Brevo
* Team
* Content
* Settings

Those will each receive their own specifications.

The next approved specification will be:

**Spec 05 — Brand Management**
