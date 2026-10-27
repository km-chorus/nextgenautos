# Spec 24 — Customer Password Recovery and Sign-in Rate Limits

**Status:** IMPLEMENTED 2026-10-22 (awaiting owner review; real emails blocked until Brevo accepts the server's IP)
**Source:** owner request of 2026-10-22:
* password recovery from the sign-in form, by a link or by a code;
* rate limiting on every authentication route;
* "try again after some time" after 3 failed passwords and after 3 reset emails;
* these messages shown as a toast with a progress bar.

## 1. Password recovery

* **Entry point:** "Forgot your password?" on the sign-in form leads to `/forgot-password/`, where the customer enters an email address.
* **What is sent:** ONE branded email (`CustomerPasswordResetService`) with a **"Set a new password" link** (`/reset-password/?t=<id>.<token>`) and a **6-digit code**. Either one works.
* **The answer:** always "If an account exists for this email address, we have sent a link and a 6-digit code". Nothing reveals whether an address has an account. Unknown or disabled accounts get no email.
* **Next page:** after a request the customer lands on `/reset-password/`, where the email is pre-filled and they enter the code and a new password twice. The link opens a form with just the two password fields.
* **Rules for a reset:**
  * 30 minutes, single use. If two submissions arrive at the same moment, only the one that deletes the cache entry may change the password.
  * A newer email cancels older links and codes.
  * 5 wrong codes cancel the reset.
* **Not in the database (owner request 2026-10-23):** the link and the code are never stored in the database. Their hashes are cached for 30 minutes in `storage/password_resets/` (`ResetCodeStore`):
  * one small file per customer;
  * the file name is an HMAC of the customer id, so it reveals nothing and can't be guessed;
  * the content is only the SHA-256 of the 32-byte token, the HMAC-SHA-256 of "customer id|code" (purpose key from APP_KEY), a wrong-code counter and the expiry time.
* **The cache entry is deleted:**
  * when the reset is used;
  * after 5 wrong codes;
  * when a newer request replaces it;
  * when it is found expired (every request and reset attempt sweeps away all expired entries).
* **Protection:** the folder is denied to web visitors (its own `.htaccess`, `storage/.htaccess`, and a 404 rule in the root `.htaccess`) and git-ignored.
* **Expired links:** "expired" is only shown when the link's secret part is correct; anything else is just "invalid".
* **On success:**
  * the new password follows PasswordPolicy;
  * the email counts as confirmed;
  * the cached reset is deleted;
  * **every other signed-in session of the account ends** (`customers.password_changed_at`, checked in `CustomerAuthService::currentCustomer`; also used by the password change in Account Settings);
  * the customer goes to sign-in with a "Your password has been changed" toast.
* **Signed-in customers** who open `/forgot-password/` are sent to Account Settings.
* **Audit events:**
  * CUSTOMER_PASSWORD_RESET_REQUESTED, _SENT, _EMAIL_FAILED, _FAILED, _DONE;
  * no email address, token, code or password is ever recorded.

## 2. Rate limits (every customer authentication route)

| Route | Limit | Answer |
|---|---|---|
| Sign in | **3 wrong passwords per account** in 15 min (even the right password is then refused); 10 failures per IP | toast "Too many failed sign-in attempts. Please try again after 15 minutes." (429) |
| Forgot password | **3 emails per address** per hour; 10 requests per IP per hour | toast "Too many requests. Please try again after some time." (429) |
| Reset (link or code) | 10 failed attempts per IP in 15 min; 5 wrong codes per reset | toast "Too many requests…" (429) / reset cancelled |
| Create account | 5 per IP per hour (spec 13) | toast "Too many requests…" (429) |
| Google sign-in start | 10 per IP in 15 min (was 20) | sign-in page with the toast |
| Confirmation email resend | 3 per hour per account (spec 22) | unchanged notice |
| Password change in Settings | 5 wrong current passwords per account in 15 min | message (2026-10-22) |

Trade-off to know: 3 wrong passwords lock that account's sign-in for 15 minutes, and anyone who knows a customer's email could trigger it. The customer can still use "Forgot your password?", which is limited separately.

## 3. Toast

* **Where and when:** one message at a time, at the bottom left, for the welcome after sign-in, rate-limit messages and confirmations.
* **Types:** success, info and error. Errors use `role="alert"`, stay 8 s and have a red progress bar. Others stay 6 s.
* **Progress bar:**
  * shrinks as the time runs out (CSS animation; durations are fixed classes because the CSP forbids inline styles);
  * pauses while the toast is hovered or focused;
  * when it runs out, the toast closes.
* **Closing early:** the close button or Escape.
* **Reduced motion:** no moving bar; a timer closes the toast instead.

## 4. Bug fixed on the way

A signed-out visitor who opened a second sign-in page (reload, or Sign in → Create account) lost the form's security token. `PublicController::hasCustomerSession` destroyed "empty" sessions, so the next submit failed with "couldn't verify your request". Sessions holding a form token or a reset in progress are now kept.

## 5. Data (migration 011, applied to dev)

* Only `customers.password_changed_at`. There is no reset table.
* The first version of 011 created `customer_password_resets`; it was removed (dropped in dev with its 2 rows, both unsent requests) before reaching the live server, at the owner's request.
* `schema.sql` is updated.
* Reset data lives only in the `storage/password_resets/` cache described in section 1.

## 6. Tests

* **`password_reset_test.php` 38/38 (with a throwaway cache folder):**
  * nothing stored in the database; the cache holds hashes only, under an unreadable name;
  * the entry is deleted when the link or code is used, after 5 wrong codes and when expired; the sweep keeps valid entries;
  * an expired link with the wrong secret says only "invalid";
  * request answers (invalid, unknown = same answer and no email, known = link and code);
  * hashes only, nothing sensitive in the audit log;
  * 3 per address and 10 per IP limits;
  * newest wins; forged and malformed links refused; password policy;
  * link reset ends other sessions and confirms the email; single use; expiry;
  * code reset (spaces allowed), single use, 5 wrong codes cancel;
  * IP throttle;
  * 3 wrong passwords throttle sign-in for the account, also from another IP.
* **`account_settings_test`** 26/26 and **`customer_auth_test`** 34/34; all other suites unchanged (known `db_test` and `vehicle_image_test` checks).
* **Browser, using addresses with no account (no email sent):**
  * the link on the sign-in form; the neutral message; the pre-filled email;
  * the 4th request shows the "Too many requests" toast (alert, 8 s bar, bar shrinks, closes by itself);
  * the 4th sign-in after 3 wrong passwords shows the sign-in toast;
  * an invalid link is explained;
  * no console errors; 375 px without sideways scrolling.
* **Not yet tested:** a real reset email. Brevo currently refuses this computer's IP (525). The owner's own request at 01:37 UTC was recorded as "email could not be sent".

## 7. Change of 2026-10-23: every form's feedback is a toast (owner request)

* **Now toasts:** the result message of every public form:
  * sign in, create account, forgot password, reset password;
  * account settings and the dashboard's confirm-email resend; sign-out;
  * the contact form, the vehicle Reserve and Ask forms;
  * cookie settings;
  * favourites ("Saved to your favourites." / "Removed from your favourites." / could not be updated).
* **How:**
  * Views call `public_toast($page, type, text)`; the footer prints one toast; the last message wins.
  * Scripts call `window.publicToast(type, text)`, which shows the same component with its progress bar, replaces the toast on screen and inserts the text as text only.
* **After sending:** the contact and vehicle forms stay usable; the "thank you" is the toast.
* **Kept inline on purpose:**
  * field errors under each input (they must stay visible while the customer corrects them; screen readers link them to the field);
  * page content that is not form feedback: the "no cookie choice yet" explanation, the confirm-email result page, an invalid or expired reset link (with its "send a new link" action), and the Track page "not on your account" box (with its Hapag-Lloyd button).
* **Not changed:** the admin area keeps its own alert style (separate design).
* **Bug fixed on the way:**
  * The favourite heart's script read `form.action`, which the form's hidden field named `action` overrides, so every save went to a wrong URL (405).
  * The old fallback hid this by reloading the page.
  * The script now uses `getAttribute('action')`: no reload, and a toast confirms.
* **Tests:** suites unchanged and passing.
* **Browser run with a temporary TEST customer (removed), 10/10 relevant checks:**
  * sign-in error and welcome toasts;
  * settings saved (or "No changes were made");
  * favourite saved and removed, one toast at a time;
  * contact and vehicle forms submitted empty (error toast, field errors inline);
  * cookie settings saved;
  * sign-out toast;
  * no inline alert boxes on these forms;
  * 375 px without sideways scrolling; no console errors.
