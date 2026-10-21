# 03 — Architecture and Data

## 1. Purpose

This document defines the technical architecture, application structure, data architecture, storage strategy, and major technical boundaries of the Vehicle Import Platform.

The goal is to keep the application organized, maintainable, secure, scalable enough for its intended use, and suitable for deployment on shared PHP hosting.

---

# 2. Approved Architecture

The application will use a:

**Modular MVC-inspired PHP monolith with MySQL, server-rendered public pages, a protected administrative application, service-layer business logic, and organized data-access components.**

The application is one unified system rather than a collection of independent services.

---

# 3. Approved Technology Stack

## Backend

* PHP 8.x
* MySQL
* PDO

## Frontend

* HTML5
* CSS3
* Vanilla JavaScript
* Bootstrap 5 OR an approved custom CSS system

## Development

* Git
* GitHub
* VS Code
* Claude Code or another approved coding agent

## Hosting

* cPanel/shared PHP hosting

## Email

* Brevo

---

# 4. Architecture Restrictions

The following must not be introduced without explicit approval:

* React SPA
* Next.js
* Laravel
* Node.js backend
* Microservices
* Serverless architecture
* GraphQL
* Redis
* Kubernetes
* Unnecessary third-party frameworks
* Additional databases
* Complex infrastructure

The architecture should remain simple and appropriate for the project.

A new technology may only be introduced when there is a clear requirement that cannot reasonably be satisfied by the existing stack.

---

# 5. Application Architecture

The preferred flow is:

```text
User Request
     ↓
Page / Route
     ↓
Controller
     ↓
Validation
     ↓
Service / Business Logic
     ↓
Repository / Model
     ↓
MySQL / File Storage
     ↓
Response / View
```

The exact implementation may vary slightly depending on the feature, but the separation of responsibilities must remain clear.

---

# 6. Controllers

Controllers are responsible for coordinating requests.

A controller may:

* Receive a request.
* Read request data.
* Call validation.
* Call the appropriate service.
* Handle the result.
* Select the appropriate response/view.

Controllers should not contain large amounts of business logic.

Controllers should not contain scattered SQL queries.

---

# 7. Services

Services contain reusable business logic.

Potential services include:

* `VehicleService`
* `ShipmentService`
* `ImageService`
* `EmailService`
* `AuthService`
* `UserService`
* `EnquiryService`
* `TeamService`
* `BrandService`

A service should represent a meaningful responsibility rather than becoming a general-purpose class containing unrelated functionality.

---

# 8. Data Access

Database access must be separated from presentation.

Use:

* PDO
* Prepared statements
* Reusable data-access methods
* Transactions where appropriate

Do not write database queries directly inside HTML templates.

Do not duplicate the same database logic across multiple pages.

---

# 9. Recommended Project Structure

The project should follow a structure similar to:

```text
vehicle-import/
│
├── app/
│   ├── config/
│   │
│   ├── controllers/
│   │
│   ├── models/
│   │
│   ├── repositories/
│   │
│   ├── services/
│   │
│   ├── validation/
│   │
│   └── helpers/
│
├── public/
│   ├── index.php
│   ├── vehicles.php
│   ├── vehicle.php
│   ├── tracking.php
│   ├── about.php
│   └── contact.php
│
├── admin/
│   ├── index.php
│   ├── dashboard.php
│   │
│   ├── vehicles/
│   ├── shipments/
│   ├── enquiries/
│   ├── team/
│   ├── brands/
│   ├── content/
│   └── settings/
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── navigation.php
│   └── auth.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── uploads/
│   ├── vehicles/
│   └── team/
│
├── docs/
│
└── specs/
```

The exact structure can be refined during implementation, but unnecessary restructuring should be avoided.

---

# 10. Public Website Architecture

Public pages should primarily:

* Retrieve approved public data.
* Render the appropriate content.
* Provide navigation.
* Handle customer interactions.
* Provide appropriate feedback.

Public pages must not expose administrative functionality or sensitive information.

---

# 11. Admin Architecture

The admin area must be separated logically from the public website.

The admin application should contain:

```text
Dashboard
Vehicles
Shipments
Enquiries
Team
Brands
Content
Settings
```

Every protected admin request must perform server-side authentication and authorization checks.

The fact that a user cannot see an admin button does not mean they are unauthorized.

---

# 12. Database Architecture

MySQL is the primary database.

The database is expected to contain entities such as:

```text
Admin Users
Brands
Vehicles
Vehicle Images
Shipments
Enquiries
Team Members
Website Content
Settings
Audit/Security Logs
```

Additional tables may be introduced when required by an approved specification.

---

# 13. Vehicle Data Model

A vehicle record should be able to represent information such as:

* ID
* Brand
* Model
* Year
* Trim
* Engine size
* Mileage
* Transmission
* Fuel type
* Drive type
* Price
* Description
* Availability status
* Shipping information
* Duty-included status
* Publication status
* Created date
* Updated date

The exact schema must be finalized before the vehicle-management specification is implemented.

---

# 14. Vehicle Images

Vehicle images should be represented separately from the main vehicle record.

A vehicle may have multiple images.

An image record should be able to identify:

* Vehicle
* File path/reference
* Image type/variant where required
* Sort order
* Primary-image status
* Relevant metadata
* Creation date

This allows multiple images to be reordered without storing image data directly inside the vehicle record.

---

# 15. Brand Architecture

Brands should be database records rather than hard-coded individually throughout the application.

This allows administrators to:

* Add brands.
* Edit brands.
* Activate/deactivate brands where appropriate.

Vehicle records should reference the appropriate brand.

The initial brands are:

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

---

# 16. Shipment Data

A shipment record should be associated with the appropriate vehicle and, where required, customer information.

Potential shipment information includes:

* Shipment ID
* Vehicle
* Customer
* Shipping company
* Container number
* Shipment status
* Shipment date
* Estimated arrival
* Tracking URL
* Internal notes
* Created date
* Updated date

The final schema must be defined by the shipment specification.

---

# 17. Container Number Architecture

Container numbers are stored as business data received from the shipping company.

The application must not generate them.

The database should distinguish between:

* No container number available yet.
* A real container number has been recorded.

A missing container number must not be replaced with fake/generated data.

---

# 18. Enquiry Data

Enquiries may contain information such as:

* Customer name
* Email
* Phone
* Message
* Related vehicle
* Enquiry type
* Status
* Created date
* Updated date

The final fields depend on the approved enquiry specification.

---

# 19. Team Data

Team members may contain:

* Name
* Position
* Biography/introduction
* Photograph
* Category/group
* Display order
* Publication status

Team information should be managed from the admin area.

---

# 20. Website Content

Content that needs regular administrative management should not unnecessarily be hard-coded into templates.

Examples may include:

* Homepage content
* Company information
* FAQ content
* Contact information
* Certain calls-to-action
* Team information

The exact content-management scope will be determined through specifications.

---

# 21. File Storage

Uploaded files should be stored in controlled upload directories.

Example:

```text
uploads/
├── vehicles/
└── team/
```

Uploaded files must not be executable.

File names should be generated safely rather than relying directly on user-provided names.

---

# 22. Image Processing Architecture

The preferred image-processing pipeline is:

```text
Admin selects images
        ↓
Browser-side optimization
        ↓
Preview
        ↓
Upload
        ↓
Server-side validation
        ↓
Image processing
        ↓
Resize / compress / convert
        ↓
WebP
        ↓
Safe storage
        ↓
Database record
```

Browser-side compression improves performance but does not replace server-side validation.

---

# 23. Image Processing Technology

The preferred PHP image-processing library is:

**Intervention Image**

The application may use:

* GD initially
* Imagick where justified and available

The final implementation must consider the capabilities of the production hosting environment.

---

# 24. Image Targets

For main vehicle images, reasonable initial targets are:

* WebP
* Maximum width around 1600px
* Quality around 75–80%
* Target file size approximately 300–700KB where practical

For thumbnails:

* WebP
* Maximum width around 600–800px
* Quality around 65–75%

These are performance targets, not absolute guarantees.

Actual file size depends on the source image.

---

# 25. Email Architecture

All application emails must use a centralized email service.

```text
Application
     ↓
EmailService
     ↓
Brevo
     ↓
Recipient
```

Controllers and templates must not contain scattered Brevo implementation code.

The application should communicate with `EmailService`, while `EmailService` handles the provider-specific implementation.

---

# 26. External Services

External services should be isolated from the rest of the application.

Current external services include:

### Ghana Duty Calculator

https://autodutychecker.com/calculator/search-mode/

### Hapag-Lloyd Tracking

https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html

### Email

Brevo

The application must not depend on an external service more deeply than necessary.

---

# 27. Database Integrity

Use:

* Primary keys
* Foreign keys where appropriate
* Unique constraints where appropriate
* Indexes
* NOT NULL constraints where appropriate
* Appropriate default values
* Transactions for multi-step operations

Database integrity should not depend entirely on frontend validation.

---

# 28. Database Performance

The database should be designed with practical performance in mind.

Use indexes for common operations such as:

* Vehicle brand
* Vehicle status
* Publication status
* Vehicle year
* Shipment/container lookup
* Enquiry status
* Frequently searched fields

Do not add indexes blindly. Indexes should support real queries.

---

# 29. Caching

Caching should not be introduced unnecessarily.

If caching becomes necessary later, it must:

* Preserve data accuracy.
* Have a clear invalidation strategy.
* Not cause customers to see outdated vehicle availability or pricing.
* Be appropriate for shared hosting.

---

# 30. Architecture Change Rule

If implementation reveals that the current architecture cannot reasonably support a requirement:

Do not silently replace the architecture.

Instead:

1. Identify the limitation.
2. Explain the technical impact.
3. Propose alternatives.
4. Assess security and hosting implications.
5. Obtain approval.
6. Update the relevant context/specification.
7. Implement the approved change.

---

# 31. Maintainability Rule

Code should be written so that another developer can understand:

* Where requests enter the system.
* Where business logic lives.
* Where database operations happen.
* Where validation happens.
* Where files are processed.
* Where emails are sent.
* Where authentication is handled.
* Where external integrations are handled.

Avoid clever code when simple, readable code is sufficient.

---

# 32. Architecture Principle

The project should remain:

**Simple enough to maintain → structured enough to grow → secure enough for production → appropriate for the hosting environment.**
