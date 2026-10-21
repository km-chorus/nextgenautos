# AI Development and Testing

## 1. Purpose

This document defines how AI coding assistants, particularly Claude Code, must work on the Vehicle Import Platform.

The purpose is to ensure that AI-assisted development remains:

* specification-driven
* predictable
* secure
* maintainable
* consistent
* testable
* aligned with the approved business requirements

AI must assist with implementation without inventing requirements or silently changing the architecture.

---

# 2. Project Constitution

The six files in `/docs` form the permanent project foundation:

```text id="g5f1yd"
/docs
    01-project-context.md
    02-scope-and-business-rules.md
    03-architecture-and-data.md
    04-design-and-ux-rules.md
    05-security-and-integrations.md
    06-ai-development-and-testing.md
```

These documents collectively act as the **project constitution**.

The AI must treat them as authoritative project context.

If existing code conflicts with these documents, the conflict must be identified rather than silently ignored.

---

# 3. Specification-Driven Development

The project will be developed one specification at a time.

The development process is:

```text id="n3s6wj"
Project Foundation
        ↓
Architecture
        ↓
Data Architecture
        ↓
Design & UX Rules
        ↓
Security Foundation
        ↓
Specification Backlog
        ↓
Specification N
        ↓
Implementation
        ↓
Testing
        ↓
Acceptance
        ↓
Specification N+1
```

The AI must not implement multiple unrelated specifications simultaneously unless explicitly instructed.

---

# 4. Specification Location

Feature specifications belong inside:

```text id="d8q2va"
/specs
```

Example:

```text id="m5v1rc"
/specs
    01-project-foundation.md
    02-database-foundation.md
    03-admin-authentication.md
    04-admin-dashboard.md
    ...
```

The exact specification numbering may change as the project backlog is finalized.

The `/specs` directory represents the approved implementation backlog.

---

# 5. Required Specification Structure

Every feature specification should contain, where applicable:

1. Purpose
2. Scope
3. User
4. Preconditions
5. Functional requirements
6. UI requirements
7. Data requirements
8. Business rules
9. Security requirements
10. Error states
11. Responsive requirements
12. Acceptance criteria
13. Out-of-scope items
14. Dependencies
15. Files/components affected
16. Testing requirements

A specification should contain enough information for implementation without requiring the AI to invent important business decisions.

---

# 6. AI Must Read Before Coding

Before implementing a specification, Claude Code must review:

1. `01-project-context.md`
2. `02-scope-and-business-rules.md`
3. `03-architecture-and-data.md`
4. `04-design-and-ux-rules.md`
5. `05-security-and-integrations.md`
6. `06-ai-development-and-testing.md`
7. the current specification
8. relevant existing code

The AI must understand the existing implementation before modifying it.

---

# 7. No Invented Requirements

The AI must not invent business requirements.

For example, if the specification says:

> Display shipment status.

The AI must not automatically decide to add:

* GPS tracking
* carrier API integration
* maps
* live location
* automatic notifications
* customer accounts

unless those requirements are explicitly approved.

The AI may recommend improvements separately, but recommendations must not silently become implementation.

---

# 8. No Silent Architecture Changes

The AI must not silently change the approved architecture.

Examples of prohibited silent changes include:

* introducing React
* replacing PHP with Node.js
* introducing Laravel
* adding Supabase
* replacing MySQL
* adding another database
* introducing microservices
* adding unnecessary APIs
* replacing server-rendered pages with an SPA
* introducing unnecessary frameworks

If the current architecture creates a legitimate technical problem, the AI must explain the problem and propose an alternative before changing the architecture.

---

# 9. One Specification at a Time

The AI must remain focused on the active specification.

If implementing:

```text
Admin Vehicle Management
```

the AI should not simultaneously redesign:

* the homepage
* contact system
* tracking system
* team pages
* unrelated database tables

unless the current specification explicitly requires those changes.

This keeps the project:

* easier to review
* easier to test
* easier to debug
* easier to revert
* easier to understand

---

# 10. Preserve Existing Functionality

When implementing a new feature, existing approved functionality must continue working.

The AI must consider possible effects on:

* navigation
* authentication
* database relationships
* public pages
* admin pages
* responsive layouts
* forms
* image handling
* email
* security controls

A new feature must not break an existing feature simply because the new feature was easier to implement that way.

---

# 11. Ask Instead of Guessing

When an ambiguity could affect:

* business logic
* pricing
* permissions
* database structure
* shipment behavior
* customer workflow
* security
* external integrations

the AI should stop and ask for clarification.

It must not make a major business decision on the user's behalf.

For minor implementation details that do not affect requirements, the AI may choose a sensible solution consistent with the architecture.

---

# 12. Technical Decisions vs Business Decisions

The AI may independently make reasonable technical decisions when they do not alter the approved requirements.

Examples:

* choosing a helper function structure
* naming a private method
* improving SQL query organization
* extracting reusable validation logic
* improving CSS organization

The AI must not independently make business decisions such as:

* changing vehicle statuses
* changing pricing behavior
* deciding who can edit shipments
* changing tracking rules
* introducing customer accounts
* changing the duty calculation process
* adding payment functionality

---

# 13. Security Before Convenience

The AI must never weaken or remove a security control simply to make implementation easier.

This includes:

* CSRF protection
* authentication
* authorization
* input validation
* output escaping
* prepared statements
* secure sessions
* upload validation
* rate limiting
* password security

If a security control causes an implementation problem, the AI must solve the implementation problem rather than remove the security control.

---

# 14. Security Review for Every Feature

Every specification must be evaluated for security implications.

Before implementation is considered complete, the AI should ask:

* What input does this feature accept?
* Can users manipulate IDs?
* Does this feature require authorization?
* Can CSRF affect it?
* Can XSS affect it?
* Can SQL injection affect it?
* Does it upload files?
* Does it expose sensitive information?
* Does it communicate with an external service?
* Does it create a new permission boundary?
* Does it need rate limiting?
* Does it create audit-log requirements?

Security must be part of implementation, not an optional later improvement.

---

# 15. Database Changes

Database changes must be deliberate.

The AI must not create unnecessary tables or fields.

Before adding a database field, determine:

1. why it is required
2. which feature uses it
3. whether an existing field can satisfy the requirement
4. whether it affects relationships
5. whether indexes are required
6. whether existing data could be affected

Database changes should be documented within the active specification.

---

# 16. Database Integrity

Where appropriate, database relationships should use:

* primary keys
* foreign keys
* unique constraints
* indexes
* non-null constraints
* appropriate defaults

The AI must consider what happens when related records are:

* deleted
* archived
* changed
* unavailable

For example, deleting a vehicle should not unintentionally destroy important shipment or enquiry records unless that behavior has been explicitly approved.

---

# 17. Code Organization

The AI must respect the approved architecture.

The preferred flow is:

```text id="f8c0zq"
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

Presentation files should not contain random database queries.

Business logic should not be scattered throughout HTML templates.

External-service logic should remain inside dedicated integration services.

---

# 18. Reusable Services

When a capability is used in multiple areas, the AI should consider creating a reusable service.

Examples:

```text id="7p4h2e"
/app/services/
    AuthService.php
    EmailService.php
    ImageService.php
    VehicleService.php
    ShipmentService.php
    ValidationService.php
```

Services should have clear responsibilities.

The AI must avoid creating giant classes that handle unrelated responsibilities.

---

# 19. Frontend JavaScript

JavaScript should be used where it improves the user experience.

Examples include:

* image previews
* image reordering
* form interactions
* filters
* mobile navigation
* password strength indicators
* loading states
* confirmation dialogs
* asynchronous UI where appropriate

JavaScript must not become the sole source of important business rules.

Important validation and authorization must remain server-side.

---

# 20. Progressive Enhancement

Where practical, core functionality should remain understandable and usable without depending entirely on JavaScript.

For example:

A vehicle page should still provide its essential information through normal server-rendered HTML.

JavaScript should enhance the experience rather than unnecessarily replacing the entire application.

---

# 21. UI Consistency

The AI must follow `04-design-and-ux-rules.md`.

It must not introduce:

* random colors
* unnecessary gradients
* purple UI
* excessive rounded cards
* emoji-based UI
* inconsistent buttons
* random typography
* unnecessary icons
* excessive animations
* generic SaaS layouts

New components should look like they belong to the same product.

---

# 22. Responsive Development

Every user-facing feature must be considered for:

* mobile
* tablet
* desktop

The AI must not simply shrink the desktop interface.

Mobile layouts should be intentionally designed.

Important areas include:

* navigation
* vehicle cards
* vehicle galleries
* specification tables
* forms
* admin tables
* buttons
* image uploads
* modals
* alerts
* empty states

---

# 23. Loading States

Where an operation takes noticeable time, the interface should provide appropriate feedback.

Possible states include:

* skeleton loading
* spinner
* progress indicator
* disabled submit button
* upload progress
* processing state

Loading feedback must communicate that the system is working without creating unnecessary visual noise.

---

# 24. Error States

Errors must be understandable and actionable.

Bad:

> SQLSTATE[HY000]...

Better:

> We couldn't save the vehicle. Please check the highlighted fields and try again.

Technical information should be logged for developers while users receive clean messages.

Errors should appear near the affected interface where possible.

---

# 25. Form Validation

Forms should provide:

* required-field indicators
* clear labels
* meaningful validation messages
* correct input types
* server-side validation
* client-side enhancement where useful
* success feedback
* loading states
* protection against duplicate submissions

Validation must not rely solely on HTML attributes or JavaScript.

---

# 26. Image Upload Testing

Vehicle and team image uploads must be tested with:

* valid JPEG
* valid PNG
* valid WebP
* oversized images
* extremely large dimensions
* invalid extensions
* renamed non-image files
* corrupted images
* multiple uploads
* duplicate filenames
* unusual filenames

The application must reject unsafe files gracefully.

---

# 27. Testing Strategy

Testing should happen continuously rather than only at the end of the project.

Testing levels include:

### 27.1 Manual Testing

Verify actual user workflows.

### 27.2 Functional Testing

Verify that each requirement works.

### 27.3 Validation Testing

Test invalid and unexpected input.

### 27.4 Security Testing

Attempt to bypass security controls.

### 27.5 Responsive Testing

Test different screen sizes.

### 27.6 Regression Testing

Verify that existing features still work after changes.

---

# 28. Acceptance Criteria

A feature is not complete merely because the code runs.

It is complete when the specification's acceptance criteria are satisfied.

For every acceptance criterion, verify:

```text id="7k3h5a"
Requirement
    ↓
Implementation
    ↓
Test
    ↓
Result
```

The AI should be able to explain how each important requirement was verified.

---

# 29. Negative Testing

Testing must include failure scenarios.

Examples:

* invalid login
* incorrect password
* expired session
* unauthorized admin access
* missing vehicle ID
* invalid vehicle ID
* duplicate vehicle data
* invalid price
* missing required fields
* invalid image
* oversized image
* invalid container number
* unavailable tracking information
* failed email delivery
* malformed URL
* missing database record

A feature that only works with perfect input is not considered complete.

---

# 30. Regression Testing

After implementing a feature, the AI should identify potentially affected existing functionality.

For example, modifying vehicle management could affect:

* homepage featured vehicles
* vehicle detail pages
* brand pages
* search
* image display
* enquiries
* admin dashboard
* database relationships

The AI should test relevant existing functionality before declaring completion.

---

# 31. Browser Testing

Important public workflows should be tested in a real browser.

At minimum, verify:

* homepage
* navigation
* vehicle listing
* vehicle details
* filters/search
* duty calculator link
* tracking page
* contact form
* responsive navigation
* admin login
* relevant admin feature

Browser testing should focus on actual user behavior rather than only source-code inspection.

---

# 32. Accessibility Testing

Every feature should consider:

* semantic HTML
* keyboard navigation
* visible focus states
* readable text
* sufficient contrast
* form labels
* meaningful button text
* image alternative text
* appropriate heading hierarchy
* accessible error messages

Accessibility should be integrated during implementation rather than retrofitted.

---

# 33. Performance Testing

The AI should avoid unnecessary performance costs.

Important considerations include:

* image optimization
* efficient database queries
* appropriate indexes
* minimized unnecessary JavaScript
* minimized unnecessary CSS
* lazy-loading appropriate images
* avoiding unnecessary external requests
* avoiding huge unoptimized assets

Vehicle photography is expected to be visually important, so image optimization is especially important.

---

# 34. Production Safety

The AI must distinguish between development and production environments.

Development-only behavior must not accidentally reach production.

Examples include:

* debug output
* test credentials
* fake vehicle data
* fake container numbers
* verbose database errors
* temporary bypasses
* development-only routes

Before deployment, these must be reviewed.

---

# 35. No Fake Production Data

AI-generated fake business data must not be presented as real production data.

This includes:

* vehicle listings
* prices
* container numbers
* customer records
* team members
* shipment statuses
* company statistics

Placeholder content must be clearly identifiable.

---

# 36. Git Discipline

The AI should make focused changes.

Changes should be:

* relevant to the active specification
* understandable
* reviewable
* free from unrelated modifications

The AI should not rewrite large parts of the project merely because it can.

Generated or temporary files should not be committed unnecessarily.

Secrets must never be committed.

---

# 37. Change Management

If implementation reveals that the specification is incomplete or incorrect, development should pause where necessary.

The change process is:

```text id="v5z9kr"
Problem Identified
        ↓
Impact Analysis
        ↓
Scope Decision
        ↓
Architecture Review
        ↓
Specification Updated/New Specification
        ↓
Implementation
        ↓
Testing
```

The AI must not silently expand the project.

---

# 38. AI Recommendation Rule

Claude Code may identify potential improvements.

However, recommendations must remain separate from implementation.

For example:

```text id="5q1b7m"
RECOMMENDATION

A future version could support automated carrier tracking through an API.

STATUS:
Not implemented.
Requires separate specification.
```

This prevents useful ideas from accidentally becoming unapproved project scope.

---

# 39. AI Completion Report

After implementing a specification, Claude Code should provide a concise completion summary containing:

### Implemented

What was built.

### Files Changed

Which files were created or modified.

### Database Changes

Any schema changes.

### Security Considerations

Security controls added or affected.

### Testing Performed

Tests that were run.

### Known Issues

Anything unresolved.

### Out of Scope

Anything intentionally not implemented.

This makes every development step reviewable.

---

# 40. Final Verification Checklist

Before declaring a specification complete, verify:

### Requirements

* [ ] Every functional requirement implemented
* [ ] No unapproved requirements added
* [ ] Acceptance criteria satisfied

### Architecture

* [ ] Approved architecture preserved
* [ ] Business logic properly separated
* [ ] No unnecessary dependencies added
* [ ] No unrelated features changed

### Security

* [ ] Authentication considered
* [ ] Authorization considered
* [ ] Input validation implemented
* [ ] SQL injection protected
* [ ] XSS protected
* [ ] CSRF protected where required
* [ ] Upload security addressed
* [ ] Rate Limiting
* [ ] Cookie and Session
* [ ] Password health checker
* [ ] Errors do not expose sensitive information

### UX

* [ ] Mobile tested
* [ ] Desktop tested
* [ ] Loading states considered
* [ ] Error states considered
* [ ] Empty states considered
* [ ] Success feedback considered
* [ ] Design system followed
* [ ] Accessibility considered

### Testing

* [ ] Happy path tested
* [ ] Invalid input tested
* [ ] Failure states tested
* [ ] Relevant regression tests performed
* [ ] Browser workflow tested

---

# 41. Claude Code Operating Rule

When Claude Code begins work on this project, it should operate according to the following principle:

> **Read the project constitution. Read the active specification. Inspect the existing implementation. Understand the requirement. Implement only what is approved. Preserve the architecture. Protect security. Test the result. Report what changed. Never silently invent functionality.**

---

# 42. Recommended Claude Code Instruction

The following instruction may be used as the project's primary AI development instruction:

```text
You are working on the Vehicle Import Platform.

The files inside /docs are the project constitution and are authoritative.

Before making changes:
1. Read all relevant files inside /docs.
2. Read the active specification inside /specs.
3. Inspect the existing code related to the specification.
4. Understand the current architecture and business rules.

Implementation rules:
- Implement only the active specification.
- Do not invent requirements.
- Do not silently expand scope.
- Do not change the approved architecture without approval.
- Do not introduce unnecessary dependencies.
- Preserve existing functionality.
- Follow the approved design system.
- Treat all client-side input as untrusted.
- Never weaken security controls for convenience.
- Keep business logic separate from presentation.
- Keep external integrations isolated.
- Validate all important input server-side.
- Use secure database access.
- Consider security implications for every feature.
- Make the interface responsive and accessible.
- Test both successful and unsuccessful scenarios.

If an ambiguity affects business logic, security, permissions, database structure, pricing, shipment behavior, or external integrations, stop and ask for clarification rather than guessing.

When implementation is complete, report:
- what was implemented
- files changed
- database changes
- security considerations
- tests performed
- known issues
- intentionally excluded functionality

Do not declare the specification complete until its acceptance criteria have been verified.
```

---

# 43. Final AI Development Principle

The AI is an implementation assistant, not the business owner or product manager.

Its responsibility is to:

> **Understand → Implement → Protect → Test → Report**

It must not:

> **Assume → Invent → Expand → Break → Hide**

The project remains controlled by the approved requirements, architecture, business rules, security standards, and specifications.
