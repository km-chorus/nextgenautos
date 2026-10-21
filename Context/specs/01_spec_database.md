
## 1. Purpose

Establish the MySQL database foundation for the Vehicle Import Platform.

This specification creates the initial relational database schema that future specifications will use.

The database must be:

* relational
* normalized where practical
* secure
* extensible
* suitable for PHP + PDO
* suitable for cPanel/shared hosting
* prepared for future growth

This specification establishes the data layer only.

Do not implement business features, frontend pages, admin authentication UI, vehicle CRUD, shipment UI, or other application features.



# 3. Database Technology

Use:

* MySQL
* InnoDB
* UTF-8 with `utf8mb4`
* PDO from the PHP application
* prepared statements

Do not use:

* PostgreSQL
* MongoDB
* SQLite as the production database
* Firebase
* Supabase
* an ORM
* a database abstraction framework

---

# 4. Database Naming

Use a consistent naming convention.

Recommended:

* lowercase table names
* snake_case
* singular or plural convention used consistently
* primary keys named `id`
* foreign keys named `<entity>_id`
* timestamps named `created_at` and `updated_at`

Use one convention consistently throughout the entire schema.

---

# 5. Core Entities

The initial schema should support these approved entities:

1. Admin Users
2. Brands
3. Vehicles
4. Vehicle Images
5. Shipments
6. Enquiries
7. Team Members
8. Website Content
9. Settings
10. Audit/Security Logs

The schema should support the relationships between these entities.

---

# 6. Admin Users Table

Create an admin users table capable of supporting secure administrative access in a later specification.

Minimum conceptual fields:

* id
* name
* email
* password_hash
* role
* status
* last_login_at
* created_at
* updated_at

Requirements:

* email must be unique
* password must never be stored in plaintext
* database column must be large enough for modern password hashes
* role should support future authorization
* status should support disabling an account
* timestamps should be stored consistently

Do not create the login system in this specification.

Do not insert a real administrator password.

If a development administrator seed is absolutely necessary, use a clearly documented temporary development credential generated securely and ensure it cannot be mistaken for a production credential.

Prefer creating the table without a user seed unless required by testing.

---

# 7. Brands Table

Create a brands table.

Minimum conceptual fields:

* id
* name
* slug
* description
* logo/image reference if required
* display_order
* status
* created_at
* updated_at

Requirements:

* brand name should be unique
* slug should be unique
* display order should support custom ordering
* status should support active/inactive brands

Seed the approved 12 brands:

* Hyundai
* Honda
* Toyota
* Mazda
* Ford
* Chevrolet
* Dodge
* Nissan
* Mercedes-Benz
* BMW
* Mitsubishi
* GMC

Use clean URL-safe slugs.

Do not add additional brands.

---

# 8. Vehicles Table

Create the main vehicles table.

It should support the vehicle information defined in the business requirements.

Minimum conceptual fields:

* id
* brand_id
* year
* make/model information
* model
* trim
* engine_size
* price
* currency
* description
* key features/specifications
* shipping information
* duty_included
* availability/status
* slug
* created_at
* updated_at

Design the schema so that brand information is related through `brand_id` rather than repeatedly storing arbitrary brand text.

The vehicle should have a unique public slug.

The schema should support:

* available vehicles
* sold vehicles
* reserved vehicles
* unavailable vehicles
* future vehicle status requirements

Do not create fake vehicle records.

Do not insert sample vehicles.

---

# 9. Vehicle Specifications / Features

Before creating a separate feature table, evaluate whether the project's current requirements genuinely require normalized vehicle feature records.

The current requirement is to display key features/specifications.

Do not over-engineer the schema.

If a structured JSON field is more appropriate for the current application and is compatible with the approved MySQL version, document that decision.

If a normalized relational structure is clearly better, implement it.

Do not create unnecessary tables simply because they are theoretically possible.

The chosen approach must be documented in the implementation report.

---

# 10. Vehicle Images Table

Create a vehicle images table related to vehicles.

It should support:

* vehicle_id
* file path/reference
* original filename if needed
* display order
* image type/role if needed
* alt text
* created_at

Requirements:

* foreign key to vehicles
* cascading behavior must be deliberate
* display ordering
* ability to identify the primary image

Do not upload or process images in this specification.

---

# 11. Shipments Table

Create a shipments table capable of representing the approved shipment workflow.

It should support concepts including:

* vehicle_id
* container number
* shipping company/carrier
* shipment status
* estimated arrival
* tracking URL
* shipped date if applicable
* arrival date if applicable
* delivered date if applicable
* created_at
* updated_at

Important business rules:

* the company does not generate container numbers
* container numbers originate from the shipping company
* live tracking comes from the external shipping provider
* the website does not fabricate tracking status
* the website does not scrape Hapag-Lloyd
* the website does not independently track the shipment

The schema must not imply that the website itself is the live tracking source.

Container number should not be automatically generated by the application.

---

# 12. Shipment Status

Support the approved shipment lifecycle:

```text
NOT_YET_SHIPPED
SHIPMENT_BOOKED
TRACKING_INFORMATION_AVAILABLE
IN_TRANSIT
ARRIVED
DELIVERED
```

Use a consistent database representation.

Do not create statuses that contradict the business rules.

If the implementation uses a lookup table rather than an ENUM, document why.

Do not hard-code assumptions about future carrier statuses.

---

# 13. Enquiries Table

Create a database structure for customer enquiries.

It should support information such as:

* id
* customer name
* email
* phone
* enquiry type
* vehicle_id if relevant
* message
* status
* created_at
* updated_at

An enquiry may relate to a specific vehicle but should also support general enquiries.

Do not implement the contact form in this specification.

Do not send emails.

---

# 14. Team Members Table

Create a team members table supporting:

* name
* position/title
* biography/introduction
* photo reference
* display order
* status
* created_at
* updated_at

This supports the company requirement to present:

* CEOs
* investors/partners where applicable
* managers
* sales team
* other employees

Do not insert fake team members.

---

# 15. Website Content Table

Create a flexible but controlled structure for website-managed content.

It should support content such as:

* About Us
* How It Works
* FAQ
* other approved editable website content

Do not create a generic uncontrolled CMS with arbitrary executable content.

The structure should allow future admin content management while keeping the system secure.

If FAQ requires a separate normalized table, explain the decision before implementing it.

---

# 16. Settings Table

Create a settings structure for controlled application/site settings.

Potential examples:

* company name
* contact information
* address
* social links
* tracking provider URL
* duty calculator URL

Do not store secrets such as:

* database passwords
* Brevo API keys
* SMTP passwords
* application secrets

Sensitive credentials belong in environment configuration, not the database.

Avoid turning the settings table into an unrestricted key-value dump.

Document what categories of settings it is intended to contain.

---

# 17. Audit/Security Logs

Create a foundational audit/security log table.

It should support events such as:

* admin login
* failed login
* logout
* password reset
* vehicle creation/update/deletion
* shipment changes
* admin account changes
* security events

Conceptual fields:

* id
* admin_user_id if applicable
* event_type
* description/context
* IP address where appropriate
* user agent where appropriate
* created_at

Never store:

* passwords
* password hashes unnecessarily
* API keys
* database credentials
* Brevo credentials
* session secrets

The log structure must not encourage sensitive data leakage.

---

# 18. Relationships

Establish appropriate foreign keys.

At minimum:

```text
brands
   ↓
vehicles
   ↓
vehicle_images

vehicles
   ↓
shipments

vehicles
   ↓
enquiries

admin_users
   ↓
audit_logs
```

Additional relationships may be added only when justified by the approved requirements.

Foreign key behavior must be deliberate.

For example, deleting a vehicle should not accidentally leave orphaned vehicle images or shipment records.

Do not use cascading deletes blindly.

For important business records, consider whether soft deletion/status changes are safer than physical deletion.

Document the decision.

---

# 19. Indexing

Add indexes for fields that will commonly be used for:

* lookup
* filtering
* searching
* sorting
* relationships

Likely candidates include:

* brand_id
* slug
* status
* vehicle year
* price
* shipment status
* container number
* enquiry status
* email
* display order

Do not create excessive indexes without justification.

Unique fields should use unique indexes where appropriate.

---

# 20. Data Integrity

Use database constraints wherever appropriate.

Examples:

* NOT NULL where required
* UNIQUE where required
* foreign keys
* valid default values
* appropriate data types
* sensible string lengths

Do not rely entirely on PHP validation for database integrity.

Application validation and database constraints should complement each other.

---

# 21. Prices and Currency

Vehicle prices must not use floating-point database types.

Use an appropriate fixed-precision numeric type for monetary values.

The current business context uses Ghanaian Cedis.

However, do not hard-code assumptions that prevent future support for other currencies.

Use a dedicated currency representation if appropriate.

Document the decision.

---

# 22. Timestamps and Timezone

Use a consistent timestamp strategy.

The application foundation already has environment/configuration support.

Database timestamps should be handled consistently across development and production.

Do not create inconsistent combinations of local server time and UTC without documenting the approach.

---

# 23. Soft Deletion

Evaluate whether important entities require soft deletion.

At minimum consider:

* vehicles
* shipments
* enquiries
* team members
* admin users

Do not automatically add `deleted_at` to every table.

If soft deletion is used, document:

* which entities use it
* why
* how queries will account for it

---

# 24. Database Setup / Schema Management

Create a reproducible database setup mechanism.

Prefer a SQL schema/migration approach that can be recreated on a new development or production database.

The exact mechanism should remain lightweight and compatible with the project's shared-hosting environment.

Do not introduce a full migration framework unless genuinely necessary.

At minimum, provide a SQL schema file or equivalent reproducible setup.

The database should be possible to recreate without manually guessing table definitions.

---

# 25. Seed Data

Only seed approved non-sensitive data.

Allowed:

* the 12 approved vehicle brands
* other clearly defined non-sensitive system/reference values if required

Do NOT seed:

* fake vehicles
* fake customers
* fake shipments
* fake enquiries
* fake team members
* fake admin accounts with production credentials

---

# 26. Tracking Provider

The approved external tracking provider is:

```text
https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html
```

The database design may support storing a tracking URL.

Do not build API integration.

Do not scrape the provider.

Do not store fabricated tracking data.

Do not create fake tracking records.

---

# 27. Duty Calculator

The approved external duty calculator is:

```text
https://autodutychecker.com/calculator/search-mode/
```

The database should not store calculated Ghana import duty unless a future specification explicitly introduces such functionality.

Do not create:

* duty calculation tables
* customs valuation logic
* duty algorithms
* fake duty values

The current application simply links customers to the external calculator.

---

# 28. Security

The database implementation must follow:

`docs/05-security-and-integrations.md`

Requirements include:

* least-privilege database user
* no credentials in Git
* PDO prepared statements
* foreign keys
* appropriate constraints
* no sensitive information in seed files
* no plaintext passwords
* no production credentials in documentation
* no unnecessary personally identifiable information

---

# 29. Database Credentials

Do not create real credentials inside the repository.

Use environment configuration.

Development credentials may be entered locally by the developer.

Production credentials must remain outside source control.

Do not commit:

```text
DB_PASSWORD=real-password
```

or equivalent.

---

# 30. Testing

Test the database foundation.

At minimum verify:

### Connection

* valid credentials connect successfully
* invalid credentials fail safely
* PHP can obtain the PDO connection

### Schema

* all expected tables exist
* primary keys exist
* foreign keys work
* unique constraints work
* required fields reject invalid data

### Relationships

Test representative relationships:

* brand → vehicle
* vehicle → vehicle images
* vehicle → shipment
* vehicle → enquiry
* admin → audit log

### Integrity

Verify:

* orphan records cannot be created where foreign keys prohibit them
* duplicate unique values are rejected
* invalid references are rejected

### Seed data

Verify all 12 approved brands exist exactly once.

---

# 31. No Application Feature Implementation

Do not build UI for:

* admin login
* dashboard
* vehicles
* brands
* shipments
* enquiries
* team
* content
* settings

The database must be ready for those features, but the features themselves belong to later specifications.

---

# 32. Files Expected

Likely files may include:

```text
database/
    schema.sql
    seed.sql
```

or an equivalent lightweight schema-management structure.

The PHP application may also require small configuration updates so the application can use the database.

Do not create unnecessary files.

Do not alter the approved architecture.

---

# 33. Acceptance Criteria

Specification 02 is complete only when:

* [ ] MySQL database schema is reproducible.
* [ ] Database uses InnoDB.
* [ ] Database uses utf8mb4.
* [ ] Admin users table exists.
* [ ] Brands table exists.
* [ ] Vehicles table exists.
* [ ] Vehicle images table exists.
* [ ] Shipments table exists.
* [ ] Enquiries table exists.
* [ ] Team members table exists.
* [ ] Website content structure exists.
* [ ] Settings structure exists.
* [ ] Audit/security logs table exists.
* [ ] Foreign keys are correctly configured.
* [ ] Appropriate indexes exist.
* [ ] Monetary values use fixed precision.
* [ ] No fake business data exists.
* [ ] The 12 approved brands are seeded.
* [ ] No real credentials are stored in Git.
* [ ] PDO successfully connects to the database.
* [ ] Database constraints have been tested.
* [ ] No unrelated application features were implemented.

---

# 34. PROGRESS.md

After successful implementation and testing:

Change:

```text
02 Database Foundation — PLANNED
```

to:

```text
02 Database Foundation — IMPLEMENTED
```

Only do this after acceptance criteria pass.

Record:

* implementation date
* schema files
* tables created
* relationships
* indexes
* seed data
* tests
* security considerations
* known issues
* next specification

Do not mark Specification 03 as implemented.

---

# 35. Required Completion Report

Report:

## Implemented

Summarize the database foundation.

## Tables Created

List every table.

## Relationships

List important foreign-key relationships.

## Indexes

Summarize important indexes.

## Seed Data

State exactly what was seeded.

## Database Changes

Describe schema/setup files.

## Security

Describe security controls.

## Testing

List database tests and results.

## Dependencies

State whether any packages were added.

## Known Issues

List unresolved issues.

## Out of Scope

Confirm that application features were not implemented.

## Next Specification

```text
Specification 03 — Admin Authentication
```

---

# 36. Stop Rule

After Specification 02 is successfully implemented and tested:

STOP.

Do not automatically implement:

* admin authentication
* admin dashboard
* vehicle management
* shipment management
* public pages
* email
* image processing
* tracking

The next specification will be provided separately after review.
