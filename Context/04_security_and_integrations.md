# Security and Integrations

## 1. Purpose

This document defines the security standards, external integrations, secrets management, validation requirements, and communication boundaries for the Vehicle Import Platform.

Security is a foundational requirement of the project and must be considered during architecture, design, development, testing, deployment, and maintenance.

The application must never sacrifice security simply to make a feature easier or faster to implement.

---

## 2. Security Philosophy

The application must follow a **defense-in-depth** approach.

No single security mechanism should be treated as sufficient.

Security must exist across:

* application code
* authentication
* authorization
* database access
* file uploads
* sessions
* forms
* external integrations
* server configuration
* administrator actions
* error handling
* logging
* backups
* deployment

The fundamental rule is:

> **Never trust client-side input or client-controlled state.**

Anything submitted or controlled by the browser must be treated as untrusted.

This includes:

* form fields
* URL parameters
* query strings
* hidden inputs
* cookies
* uploaded files
* JavaScript values
* IDs
* headers
* tracking numbers
* vehicle IDs
* status values
* prices
* permissions
* user-supplied text

---

# 3. Authentication

## 3.1 Admin Authentication

The administrative application must require authentication.

Public website visitors must not have access to administrative functionality.

Admin authentication must include:

* username/email
* password
* secure login process
* session creation
* logout
* failed-login handling
* password reset capability

---

## 3.2 Password Storage

Passwords must never be stored as plain text.

PHP's password hashing functionality must be used:

* `password_hash()`
* `password_verify()`

The database must store only the resulting password hash.

Passwords must never appear in:

* source code
* GitHub
* configuration committed to Git
* logs
* error messages
* database exports
* email messages

---

## 3.3 Session Security

Admin sessions must be securely managed.

The application must:

* regenerate the session ID after successful login
* use secure session cookies
* use `HttpOnly`
* use an appropriate `SameSite` policy
* use `Secure` when HTTPS is enabled
* destroy the session during logout
* expire inactive sessions after a reasonable period
* prevent session fixation

The application must not rely on hiding or obscuring the admin URL as a security mechanism.

---

# 4. Authorization

Authentication answers:

> Who are you?

Authorization answers:

> What are you allowed to do?

These must remain separate concerns.

The application must verify permissions on the server before performing administrative actions.

Initial roles may be simple, but the architecture should support role-based permissions.

Potential future roles include:

* Super Admin
* Administrator
* Sales Manager
* Sales Staff
* Content Manager

Roles must not automatically grant unrestricted access unless explicitly defined.

---

# 5. Object-Level Access Control

Every protected object must be checked before modification or deletion.

For example:

```text
/admin/vehicles/edit.php?id=25
```

The application must not assume that because an administrator is logged in, they automatically have permission to modify vehicle ID `25`.

The server must verify:

1. the object exists
2. the object is accessible
3. the administrator has permission
4. the requested operation is allowed

This prevents IDOR-style vulnerabilities.

---

# 6. Input Validation

All user-controlled data must be validated server-side.

Validation must occur before business logic or database operations.

Examples include:

* required fields
* maximum lengths
* valid email addresses
* valid numeric values
* valid dates
* valid status values
* valid IDs
* valid URLs
* valid image files
* valid vehicle information
* valid container numbers

Client-side validation may improve user experience, but it must never replace server-side validation.

---

# 7. SQL Injection Protection

All database queries involving dynamic values must use PDO prepared statements.

The application must not construct SQL queries by concatenating raw user input.

Example principle:

```text
User input
    ↓
Validation
    ↓
Prepared statement
    ↓
Database
```

Database access should be centralized through the application's repository/model/data-access layer.

---

# 8. Cross-Site Scripting Protection

User-controlled content must never be rendered directly into HTML without appropriate escaping.

This applies to:

* vehicle descriptions
* vehicle features
* team biographies
* enquiries
* names
* email addresses
* content-management fields
* URL parameters
* error messages

Output escaping must be context-appropriate.

The application must distinguish between:

* HTML content
* HTML attributes
* URLs
* JavaScript contexts
* CSS contexts

---

# 9. CSRF Protection

All state-changing forms must use CSRF protection.

This includes administrative actions such as:

* creating vehicles
* editing vehicles
* deleting vehicles
* changing vehicle status
* creating shipments
* editing shipments
* deleting records
* managing team members
* changing website content
* changing settings
* changing administrator information
* password changes

CSRF tokens must be:

* generated server-side
* associated with the session
* validated server-side
* invalidated or rotated appropriately where necessary

A request must not be considered legitimate simply because it came from a browser.

---

# 10. File Upload Security

File uploads represent a major security boundary.

Vehicle and team image uploads must be treated as untrusted files.

The server must independently validate uploaded files.

Validation should include:

* file size
* actual MIME type
* file contents
* image dimensions
* supported formats
* filename safety
* image integrity

Initially supported image formats:

* JPEG
* PNG
* WebP

The application must not trust the filename extension alone.

---

# 11. Image Processing Security

Browser-side image compression is a performance feature, not a security feature.

Even if an image has already been compressed in the browser, the server must validate and process it again.

The server-side pipeline should be:

```text
Browser Upload
      ↓
Server Validation
      ↓
Image Processing
      ↓
Resize
      ↓
Convert to WebP
      ↓
Compress
      ↓
Generate Safe Filename
      ↓
Store
      ↓
Save Metadata
```

The application should use the approved image-processing service rather than allowing arbitrary uploaded files to be stored unchanged.

---

# 12. Uploaded File Storage

Uploaded files must not be executable.

The upload directory must be configured so that uploaded content cannot be interpreted as executable PHP code.

The application should use generated filenames rather than trusting original filenames.

Example:

```text
vehicle_8f3a92d1.webp
```

rather than:

```text
my-new-car.php
```

Original filenames should not become the application's storage filename.

---

# 13. Database Security

The application database must use a dedicated database account.

The account should have only the permissions required by the application.

Database credentials must not be committed to GitHub.

Database access must be centralized through the application's database configuration/service layer.

Database integrity should be enforced using:

* primary keys
* foreign keys
* unique constraints
* appropriate indexes
* non-null constraints where appropriate
* sensible default values
* transactions where multiple related operations must succeed together

---

# 14. Secrets Management

The following must never be committed to the Git repository:

* database passwords
* Brevo credentials
* API keys
* SMTP passwords
* secret tokens
* encryption keys
* production credentials
* administrator passwords
* private certificates

Secrets should be supplied through secure server configuration or environment-based configuration where supported by the hosting environment.

A `.gitignore` file must prevent sensitive local configuration files from being committed.

---

# 15. HTTPS

Production traffic must use HTTPS.

The application must:

* operate over HTTPS
* redirect HTTP to HTTPS where appropriate
* avoid transmitting credentials over HTTP
* use secure cookies
* avoid mixed-content resources

The production website must not treat HTTP as an acceptable alternative for authenticated functionality.

---

# 16. Security Headers

The application should use appropriate HTTP security headers.

These may include:

* Content-Security-Policy
* X-Content-Type-Options
* Referrer-Policy
* Permissions-Policy
* frame protection such as `frame-ancestors` through CSP

Security headers must be configured carefully so that they improve security without unnecessarily breaking legitimate functionality.

The Content Security Policy must account for approved external services such as:

* Brevo-related resources where required
* approved analytics if later added
* approved external tracking links
* approved fonts/assets if externally hosted

External resources must not be added casually.

---

# 17. Rate Limiting

Rate limiting should protect sensitive and abuse-prone endpoints.

Initial candidates include:

* admin login
* password reset
* contact form
* vehicle enquiry form
* tracking lookup
* other public forms

Rate limiting should reduce:

* brute-force attempts
* automated spam
* form abuse
* excessive requests
* unnecessary server load

Rate limiting implementation must be compatible with the hosting environment.

---

# 18. Spam Protection

Public forms must include reasonable anti-spam protection.

The initial strategy may include:

* server-side validation
* rate limiting
* honeypot fields
* minimum submission timing checks where appropriate

CAPTCHA may be introduced if real-world spam becomes significant or if required by a later specification.

CAPTCHA must not be added simply because it is commonly used.

---

# 19. Error Handling

Production users must never receive sensitive technical errors.

The application must not expose:

* database credentials
* SQL queries
* filesystem paths
* stack traces
* PHP warnings containing sensitive information
* API credentials
* internal server configuration

Users should receive clean, understandable error messages.

Example:

> Something went wrong while processing your request. Please try again.

Detailed technical information should be logged securely for administrators/developers.

---

# 20. Logging

Security-relevant actions should be logged.

Important events include:

* successful admin login
* failed admin login
* logout
* password reset
* password changes
* vehicle creation
* vehicle updates
* vehicle deletion
* shipment creation
* shipment updates
* tracking information changes
* administrator account changes
* permission changes

Logs must not contain:

* passwords
* API secrets
* session secrets
* sensitive credentials

Logging must be useful without becoming a source of sensitive-data exposure.

---

# 21. Audit Trail

Administrative changes affecting important business data should be traceable.

Where appropriate, audit records should identify:

* administrator
* action
* affected record
* timestamp
* relevant change information

This is especially important for:

* vehicle pricing
* vehicle availability
* vehicle deletion
* shipment information
* container numbers
* shipment status
* administrator permissions

---

# 22. External Integration Philosophy

External services must be isolated behind dedicated application services.

Business logic should not directly depend on third-party SDK calls throughout controllers or views.

The architecture should follow:

```text
Controller
    ↓
Application Service
    ↓
Integration Service
    ↓
External Provider
```

This makes integrations easier to:

* test
* replace
* disable
* troubleshoot
* secure
* maintain

---

# 23. Brevo Email Integration

The approved email provider for the project is:

**Brevo**

The application must use a centralized:

```text
EmailService
```

Recommended location:

```text
/app/services/EmailService.php
```

The rest of the application should communicate with `EmailService` rather than directly calling Brevo throughout the codebase.

---

# 24. Email Responsibilities

Email functionality may support:

### Customer communications

* enquiry confirmation
* vehicle enquiry confirmation
* shipment/tracking notification
* other approved transactional messages

### Internal communications

* new enquiry notification
* vehicle enquiry notification
* shipment-related notification
* administrative alerts
* password reset

Only approved transactional email use cases should be implemented.

---

# 25. Brevo Credentials

Brevo credentials must remain server-side.

They must never appear in:

* frontend JavaScript
* HTML
* browser-visible network payloads
* GitHub
* public configuration
* client-side environment variables

The browser should communicate with the application's backend.

The backend communicates with Brevo.

```text
Customer
   ↓
Website
   ↓
EmailService
   ↓
Brevo
```

---

# 26. Email Failure Handling

Email failure must not automatically mean the entire business operation failed.

For example:

```text
Vehicle enquiry saved successfully
        ↓
Email notification attempted
        ↓
Email succeeds → continue
Email fails → log failure and handle gracefully
```

The application must distinguish between:

* business operation failure
* email delivery failure

This prevents a temporary email-provider problem from unnecessarily preventing valid customer enquiries from being recorded.

---

# 27. Vehicle Duty Calculator Integration

The website does not calculate Ghana import duty.

The approved duty-calculation experience is an external service.

The website must provide:

* accurate vehicle information
* relevant vehicle specifications
* a clear duty-calculation call-to-action
* an external link to the approved duty calculator

The application must not claim to calculate the customer's duty itself.

The website must not invent:

* duty amounts
* customs values
* tax values
* estimated customs fees

The external calculator remains responsible for the actual calculation.

---

# 28. Vehicle Tracking Integration

The website is an access layer to the external shipping/tracking provider.

The website does not generate container numbers.

The website does not invent tracking information.

The website does not pretend to know live shipment status when it does not.

The approved business flow is:

```text
Customer purchases vehicle
        ↓
Company arranges shipment
        ↓
Shipping company assigns container
        ↓
Shipping company provides container number
        ↓
Import company receives container number
        ↓
Admin records container number
        ↓
Company communicates container number to customer
        ↓
Customer visits Track Vehicle
        ↓
Website directs customer to official tracking provider
        ↓
Customer tracks shipment externally
```

---

# 29. Tracking Source of Truth

The shipping/tracking provider is the source of truth for live shipment tracking.

The website's database may store administrative shipment information such as:

* vehicle
* customer
* container number
* shipping company
* shipment status
* estimated arrival
* tracking URL where available
* internal notes where approved

However, the website must not present its own fabricated live location or shipment status as carrier-confirmed information.

---

# 30. Tracking Number Rules

A container number must only be entered into the system when it has been provided by the legitimate shipping company.

The system must not automatically generate container numbers.

The system must not generate fake placeholder tracking numbers for production customers.

Development/test data must be clearly identified as test data.

If the container number has not yet been provided:

> Tracking information is not yet available.

The user should be told that the company will provide the container number once the vehicle has been assigned to a shipment.

---

# 31. External Tracking Links

The tracking page should direct customers to the approved external tracking provider.

If the shipping provider supplies a specific tracking URL, the system may store and display that URL when permitted.

Otherwise, the website may direct customers to the official tracking page and explain how to use the container number.

The application must not scrape external tracking pages unless a future specification explicitly approves such functionality.

---

# 32. Live Carrier API Integration

Live shipping-provider API integration is **out of scope** for the current project.

The system must not silently introduce:

* carrier APIs
* automated tracking synchronization
* web scraping
* background tracking workers
* live GPS data

Any future live integration requires:

1. business approval
2. technical feasibility review
3. security review
4. architecture impact analysis
5. new specification
6. implementation and testing

---

# 33. Integration Failure States

External services can fail.

The application must gracefully handle situations such as:

* email provider unavailable
* external tracking website unavailable
* invalid external URL
* network timeout
* temporary service failure

The website should provide clear user-facing feedback without exposing technical details.

---

# 34. Dependency Control

External packages and services must not be added without justification.

Before introducing a dependency, evaluate:

* necessity
* security
* maintenance
* hosting compatibility
* performance
* licensing
* project complexity
* long-term reliability

A package should solve a real project requirement rather than being added for convenience.

---

# 35. Security and Convenience Rule

The following principle is mandatory:

> **Convenience must never override security.**

Examples:

Do not:

* disable CSRF because a form is difficult to submit
* remove authorization checks because they cause development errors
* expose API credentials to frontend code
* accept unvalidated uploads because browser validation works
* disable password security to simplify login
* expose database errors to help debugging in production
* make the admin panel publicly accessible
* bypass permissions to make a feature work

When a security mechanism creates implementation difficulty, the implementation must be corrected rather than the security mechanism removed.

---

# 36. Security Review Before Release

Before a feature is considered complete, verify:

* authentication works correctly
* authorization is enforced
* inputs are validated
* SQL uses prepared statements
* outputs are escaped
* CSRF protection exists where required
* sessions are secure
* uploads are validated
* uploaded files cannot execute
* secrets are protected
* external integrations do not expose credentials
* errors do not expose sensitive information
* rate limiting exists where required
* permissions cannot be bypassed
* important admin actions are logged

Security-sensitive features must not be considered complete until these checks have been addressed.

---

# 37. Integration Change Rule

Changing an external integration is an architectural decision.

Examples include:

* changing Brevo
* adding a payment provider
* adding a carrier API
* replacing the duty calculator
* adding WhatsApp automation
* adding analytics
* adding CAPTCHA
* adding a new external storage provider

Such changes require an approved specification or change request.

They must not be introduced casually during implementation.

---

# 38. Final Security Principle

The Vehicle Import Platform must be designed so that:

> **The browser is never trusted, the server validates every important operation, sensitive credentials remain server-side, external services are isolated, administrative actions are protected, and real-world business information is never fabricated by the application.**

Security is part of the architecture, not a final checklist added immediately before launch.
