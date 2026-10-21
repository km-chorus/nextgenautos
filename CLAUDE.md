# Vehicle Import Platform — Claude Code Instructions

## 1. READ THIS FIRST

You are working on the **Vehicle Import Platform**, a professional vehicle-import website and administrative platform for a company operating between Canada and Ghana.

Before doing any development work, you MUST read:

```text
CLAUDE.md

context/01-project-context.md
context/06-scope-and-business-rules.md
context/02-architecture-and-data.md
context/03-design-and-ux-rules.md
context/04-security-and-integrations.md
context/05-ai-development-and-testing.md

PROGRESS.md

The active specification inside /specs
```

These files define the project's approved context.

---

# 2. AUTHORITY HIERARCHY

When making implementation decisions, use this priority order:

```text
1. Actual approved business requirements
2. Project business rules
3. Active specification
4. Security requirements
5. Approved architecture
6. Design and UX rules
7. Technical convenience
```

Technical convenience must never override a higher-priority rule.

If two project documents appear to conflict, do not silently choose one.

Identify the conflict and ask for clarification.

---

# 3. PROJECT FOUNDATION

The permanent project context is stored in:

```text
/docs
```

The implementation backlog is stored in:

```text
/specs
```

Project progress is tracked in:

```text
PROGRESS.md
```

This file is:

```text
CLAUDE.md
```

The relationship is:

```text
CLAUDE.md
      ↓
Project Constitution
      ↓
Active Specification
      ↓
Implementation
      ↓
Testing
      ↓
PROGRESS.md
```

---

# 4. CORE PROJECT RULE

You are an implementation assistant.

You are NOT the:

* business owner
* product manager
* project manager
* UX decision maker
* security approver

Do not invent important business decisions.

Your job is:

```text
Understand
    ↓
Implement
    ↓
Protect
    ↓
Test
    ↓
Report
```

Not:

```text
Assume
    ↓
Invent
    ↓
Expand
    ↓
Break
    ↓
Hide
```

---

# 5. SPECIFICATION-DRIVEN DEVELOPMENT

Only implement the active specification.

Do not implement features simply because they seem useful.

Do not implement future features early.

Do not combine unrelated specifications.

If the active specification is:

```text
/specs/06-vehicle-management.md
```

focus on that specification.

Do not simultaneously redesign unrelated areas unless the specification explicitly requires them.

---

# 6. BEFORE CODING

Before writing code:

### Step 1

Read the project foundation.

### Step 2

Read `PROGRESS.md`.

### Step 3

Identify the active specification.

### Step 4

Read the entire active specification.

### Step 5

Inspect the existing project structure.

### Step 6

Inspect relevant existing files.

### Step 7

Understand the database relationships involved.

### Step 8

Identify security implications.

### Step 9

Identify dependencies.

### Step 10

Implement only after understanding the requirement.

---

# 7. DO NOT GUESS IMPORTANT REQUIREMENTS

If ambiguity affects:

* business logic
* pricing
* vehicle status
* shipment status
* tracking
* permissions
* database relationships
* authentication
* security
* external integrations
* customer workflow

STOP and ask for clarification.

Do not make a major business decision silently.

For minor implementation details that do not affect the approved requirements, use a reasonable solution consistent with the project architecture.

---

# 8. ARCHITECTURE

The approved architecture is:

> Modular MVC-inspired PHP monolith with MySQL, PDO, server-rendered public pages, a protected administrative application, service-layer business logic, repository/model-based data access, dedicated services for security, validation, media processing and external integrations.

Approved technology direction:

```text
PHP 8.x
MySQL
PDO
HTML5
CSS3
Vanilla JavaScript
Bootstrap 5 or approved custom CSS
Git/GitHub
cPanel/shared hosting
Brevo
```

Do NOT silently introduce:

* React
* Next.js
* Laravel
* Node.js backend
* Supabase
* Firebase
* MongoDB
* PostgreSQL
* microservices
* serverless architecture
* GraphQL
* Redis
* unnecessary frameworks
* unnecessary dependencies

If architecture must change, explain why and obtain approval first.

---

# 9. CODE ORGANIZATION

Use the approved architectural flow:

```text
Request
   ↓
Controller / Route
   ↓
Validation
   ↓
Service / Business Logic
   ↓
Repository / Model
   ↓
Database
   ↓
View / Response
```

Do not put random database queries inside presentation files.

Do not put business logic inside HTML templates.

Do not scatter external API calls throughout the application.

Use dedicated services where appropriate.

---

# 10. SECURITY IS MANDATORY

Treat all browser-controlled data as untrusted.

This includes:

* forms
* URLs
* query parameters
* IDs
* hidden inputs
* cookies
* headers
* uploads
* JavaScript values

Always consider:

* authentication
* authorization
* CSRF
* XSS
* SQL injection
* session security
* file upload security
* IDOR
* rate limiting
* spam
* secrets
* error disclosure

Never weaken a security control just to make implementation easier.

---

# 11. DATABASE RULES

Use PDO prepared statements.

Never concatenate untrusted values directly into SQL queries.

Use appropriate:

* primary keys
* foreign keys
* indexes
* unique constraints
* constraints
* transactions

Do not create unnecessary tables or fields.

Before changing the schema, understand the impact on existing data and relationships.

---

# 12. AUTHENTICATION RULES

Admin passwords must use:

```text
password_hash()
password_verify()
```

Never store plaintext passwords.

Admin sessions must:

* regenerate after login
* use secure cookie settings
* use HttpOnly
* use appropriate SameSite protection
* use Secure under HTTPS
* expire appropriately
* be destroyed on logout

Authentication and authorization must remain separate.

---

# 13. FILE UPLOAD RULES

Never trust uploaded filenames or extensions.

Supported image formats:

```text
JPEG
PNG
WebP
```

Validate uploads server-side.

The server must check:

* MIME/content
* size
* dimensions
* file validity
* filename safety

Uploaded files must not be executable.

Use safe generated filenames.

Browser-side compression does NOT replace server-side validation.

---

# 14. IMAGE PROCESSING

Use the approved image-processing architecture.

Preferred tools:

```text
Browser:
browser-image-compression

Server:
Intervention Image

Engine:
GD initially
Imagick if required
```

Preferred output:

```text
WebP
```

The server remains responsible for final processing and validation.

---

# 15. EMAIL

The approved email provider is:

**Brevo**

All application email functionality must be centralized through:

```text
/app/services/EmailService.php
```

Do not scatter Brevo integration code across controllers and views.

Brevo credentials must remain server-side.

Never expose credentials in frontend JavaScript or GitHub.

---

# 16. VEHICLE DUTY RULE

The website does NOT calculate Ghana import duty.

It only:

* displays accurate vehicle information
* provides the appropriate vehicle details
* directs customers to the approved external duty calculator

Do not invent duty values.

Do not build an internal duty calculator unless a future approved specification explicitly requires it.

---

# 17. TRACKING RULE

The website does NOT generate container numbers.

The shipping company provides the real container number.

The business process is:

```text
Customer purchases
        ↓
Company arranges shipment
        ↓
Shipping company assigns container
        ↓
Shipping company provides container number
        ↓
Company records it
        ↓
Company communicates it to customer
        ↓
Customer uses tracking page
        ↓
Official external tracking provider
```

Do not fabricate:

* container numbers
* shipment locations
* live tracking information
* carrier statuses

Do not scrape the tracking provider.

Do not implement carrier API integration without a new approved specification.

---

# 18. DESIGN RULES

Follow:

```text
docs/04-design-and-ux-rules.md
```

The design must be:

* professional
* mature
* clean
* premium
* structured
* mobile-first
* automotive/business oriented

Do NOT introduce:

* purple
* emojis
* generic SaaS visuals
* excessive cards
* excessive rounded corners
* unnecessary gradients
* heavy animations
* visual clutter
* inconsistent colors
* random typography

Every visual element must serve a meaningful purpose.

---

# 19. RESPONSIVE RULE

Every feature must work on:

* mobile
* tablet
* desktop

Do not simply shrink desktop layouts.

Mobile layouts must be intentionally designed.

---

# 20. UX RULES

Consider:

* loading states
* skeleton states
* empty states
* error states
* success states
* disabled states
* validation states
* accessibility
* keyboard interaction

Errors must be understandable to users.

Technical errors belong in secure logs, not user-facing interfaces.

---

# 21. NO FAKE BUSINESS DATA

Do not present invented information as real.

Never invent production:

* vehicles
* prices
* team members
* customers
* container numbers
* shipment statuses
* company statistics

Development data must be clearly identifiable as test/demo data.

---

# 22. PRESERVE EXISTING FUNCTIONALITY

Before changing existing code:

* understand what it does
* identify dependencies
* avoid unnecessary rewrites
* preserve working functionality

Do not rewrite the entire application when a focused change is sufficient.

Do not modify unrelated files without a reason.

---

# 23. DEPENDENCY RULE

Do not install a package simply because it is convenient.

Before introducing a dependency, determine:

* why it is necessary
* whether native PHP/JS can solve it
* security implications
* maintenance implications
* hosting compatibility
* performance impact
* licensing

Prefer a simpler solution when appropriate.

---

# 24. TESTING

Every specification must be tested.

At minimum consider:

### Functional

Does the feature work?

### Validation

Does invalid input fail correctly?

### Security

Can the feature be bypassed?

### Responsive

Does it work on mobile and desktop?

### Regression

Did the feature break anything existing?

### Accessibility

Can users navigate and understand it?

### Error handling

Are failure states handled cleanly?

---

# 25. ACCEPTANCE CRITERIA

Do not mark a feature complete because the code exists.

A feature is complete only when:

* requirements are implemented
* acceptance criteria pass
* security requirements pass
* responsive behavior is verified
* error states are verified
* relevant regression testing passes
* no critical known issues remain

---

# 26. PROGRESS TRACKING

After meaningful implementation work, update:

```text
PROGRESS.md
```

Keep the following accurate:

* current status
* active specification
* completed work
* next task
* blockers
* testing state
* known issues

Never mark something `COMPLETED` before acceptance criteria have been verified.

---

# 27. STATUS FLOW

Use:

```text
PLANNED
   ↓
IN PROGRESS
   ↓
IMPLEMENTED
   ↓
TESTING
   ↓
PASSED
   ↓
COMPLETED
```

If problems are discovered:

```text
TESTING
   ↓
NEEDS REVISION
   ↓
IN PROGRESS
   ↓
TESTING
```

If blocked:

```text
IN PROGRESS
   ↓
BLOCKED
```

---

# 28. CHANGE CONTROL

If you discover that the current specification is incomplete:

Do not silently expand the implementation.

Instead:

```text
Problem
   ↓
Impact Analysis
   ↓
Ask / Obtain Decision
   ↓
Update Specification
   ↓
Implement
   ↓
Test
```

---

# 29. RECOMMENDATIONS

You may recommend future improvements.

But recommendations must not automatically become implementation.

Clearly label them:

```text
RECOMMENDATION
```

and explain:

* what the improvement is
* why it may be useful
* whether it affects architecture
* whether a new specification is required

---

# 30. COMPLETION REPORT

After implementing a specification, provide:

```text
## Implementation Summary

### Implemented
- ...

### Files Created
- ...

### Files Modified
- ...

### Database Changes
- ...

### Security
- ...

### Testing
- ...

### Known Issues
- ...

### Out of Scope
- ...

### Progress
- Updated PROGRESS.md
```

Do not claim tests were performed if they were not actually performed.

---

# 31. GIT DISCIPLINE

Keep changes focused.

Do not:

* commit secrets
* commit credentials
* commit temporary files
* commit unnecessary dependencies
* modify unrelated code

Before considering a task complete, inspect the changes made.

---

# 32. FINAL RULE

When uncertain, do not guess about important project decisions.

Read the project documents first.

If the answer is still unclear:

**Ask.**

The goal is not to write the most code.

The goal is to build the correct system, securely and consistently, one approved specification at a time.
