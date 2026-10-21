# 02 — Scope and Business Rules

## 1. Purpose

This document defines the functional scope and business rules of the Vehicle Import Platform.

It establishes what the platform is expected to do, what it must not do, and how important business processes must behave.

These rules apply across the entire project.

No feature outside the approved scope may be added without an explicit change request and specification.

---

# 2. In-Scope Features

## 2.1 Public Website

The public website includes:

* Homepage
* Vehicle inventory
* Vehicle search
* Vehicle filtering
* Vehicle details
* Brand browsing
* Vehicle specifications
* Vehicle pricing
* Vehicle availability
* Shipping information
* Duty information
* External Ghana import-duty calculator access
* Vehicle tracking access
* How It Works
* About Us
* Our Team
* Contact Us
* FAQ
* SEO-friendly vehicle pages
* Responsive/mobile-first experience

---

## 2.2 Vehicle Inventory

The inventory must allow customers to:

* Browse vehicles.
* Search for vehicles.
* Filter vehicles.
* Browse vehicles by brand.
* Open individual vehicle details.
* View vehicle photographs.
* View vehicle specifications.
* View pricing.
* View availability.
* View relevant shipping information.
* Contact the company about a vehicle.

The inventory must be based on real vehicle records managed by authorized administrators.

---

## 2.3 Vehicle Management

Administrators should be able to:

* Add vehicles.
* Edit vehicles.
* Remove/archive vehicles where appropriate.
* Update vehicle information.
* Update pricing.
* Update availability.
* Add vehicle features.
* Add and manage vehicle photographs.
* Reorder photographs.
* Set a primary vehicle photograph.
* Publish/unpublish vehicles where applicable.

---

# 3. Out of Scope

The following features are explicitly outside the current project scope.

They must not be added automatically.

### Applications

* Native Android application
* Native iOS application
* Windows desktop application

### Financial Services

* Cryptocurrency payments
* Crypto wallets
* Financing system
* Loan management
* Automated international payment processing
* Accounting system
* Payroll system

### Automotive/Logistics

* Auction bidding
* Insurance management
* Customs-clearance management
* Automatic Ghana Customs valuation
* Automatic Ghana import-duty calculation
* Live GPS tracking
* Fleet management
* Spare-parts inventory

  Note (owner, 2026-10-14): the business also sells car parts for popular makes (for example Toyota, Honda, Hyundai). The public website now says so in its copy (homepage hero, trust cards, About, footer, meta description). A parts inventory, catalogue, prices or ordering remain OUT OF SCOPE until a new specification is approved. Update (owner, 2026-10-14): the owner wants a parts catalogue in the admin and on the site; draft `Context/specs/16_spec_parts_catalogue.md` (catalogue with category, part number, condition, public price, fitments; no online ordering) moves it into scope once approved. Not yet known, and not to be stated on the site until confirmed: where parts are sourced, new or used, which makes exactly, and how customers enquire.

### Marketplace Features

* Seller marketplace
* Multi-vendor marketplace
* Customer social network

### Automation/AI

* AI chatbot
* AI recommendation engine
* Automated WhatsApp bot
* Complex CRM automation

### Carrier Integrations

* Hapag-Lloyd API integration
* Automatic live carrier API integrations
* Shipment scraping
* Independent live tracking infrastructure

These features require a separate approved change request and specification.

---

# 4. Vehicle Business Rules

## 4.1 Vehicle Accuracy

Vehicle information displayed to customers must represent the actual vehicle.

The system must not invent:

* Vehicle specifications
* Mileage
* Engine information
* Features
* Pricing
* Shipping information
* Availability
* Vehicle history

If information is unavailable, the interface should communicate that appropriately instead of inventing a value.

---

## 4.2 Vehicle Status

Vehicle availability must reflect the actual business state.

The initial system may support statuses such as:

* Available
* Reserved
* Sold

Additional statuses require an approved specification.

---

## 4.3 Vehicle Images

Vehicle images must correspond to the vehicle they are attached to.

Administrators should be able to:

* Upload multiple images.
* Preview images.
* Reorder images.
* Remove images.
* Select a primary image.

The system must prevent unsafe files from being uploaded.

---

# 5. Pricing Rules

Vehicle prices must come from authorized vehicle records.

The system must not:

* Invent prices.
* Automatically modify prices without an approved business rule.
* Display outdated cached prices where the architecture can reasonably prevent it.
* Represent a price as including duty unless the vehicle record explicitly states this.

Where applicable, the interface must clearly distinguish:

* Vehicle price
* Shipping information
* Duty-included status
* Other applicable charges

The exact presentation will be defined in the relevant feature specification.

---

# 6. Ghana Import Duty Rules

## 6.1 No Internal Duty Calculation

The website does **not** calculate Ghana import duty.

It must not attempt to recreate the external calculator.

The website's responsibility is to:

1. Display accurate vehicle information.
2. Help the customer identify the information required by the external calculator.
3. Provide a link to the approved external calculator.
4. Clearly communicate that the calculation is performed externally.

Approved external calculator:

https://autodutychecker.com/calculator/search-mode/

---

## 6.2 Duty Information

Where vehicle information is displayed for duty calculation, it must be clear enough for customers to identify the relevant vehicle details.

The website must not present an internally generated amount as an official customs-duty figure.

The result of the external calculator remains outside the website's control.

---

# 7. Shipment and Tracking Business Rules

## 7.1 Actual Business Process

The approved shipment process is:

**Customer purchases/engages company**

→ **Company arranges shipment**

→ **Shipping company assigns container**

→ **Shipping company provides container number**

→ **Import company receives container number**

→ **Administrator records container number**

→ **Company communicates container number to customer**

→ **Customer accesses tracking page**

→ **Customer tracks through official external provider**

---

## 7.2 Container Number Rules

The container number:

* Comes from the shipping company.
* Must be a real identifier.
* Must never be generated by the website.
* Must never be fabricated.
* Must be recorded by an authorized administrator.
* May be communicated to the customer when available.

The website is not responsible for creating the container number.

---

## 7.3 Tracking Page

The tracking page should allow the customer to access official tracking information.

The website may:

* Explain how tracking works.
* Explain when a container number becomes available.
* Provide a place to enter/access the relevant tracking information if required by the approved UI.
* Direct the customer to the official tracking provider.
* Display a stored official tracking link when one exists.

The website must not claim to provide live tracking unless a real approved integration exists.

---

## 7.4 Tracking Information Availability

Before the shipping company provides a container number, the website must not imply that the customer should already have one.

The interface should provide a clear state such as:

**Tracking information not yet available.**

The customer should be told that the container number will be provided by the company once it becomes available.

---

## 7.5 Tracking Source of Truth

The external shipping/tracking provider is the source of truth for live shipment information.

The website must not:

* Fabricate shipment statuses.
* Guess estimated locations.
* Scrape carrier websites.
* Pretend to have live tracking.
* Display outdated tracking information as live information.
* Create fake container numbers.

---

# 8. Shipment Statuses

The initial shipment lifecycle may include:

1. **Not Yet Shipped**
2. **Shipment Booked**
3. **Tracking Information Available**
4. **In Transit**
5. **Arrived**
6. **Delivered**

These statuses describe the company's administrative understanding of the shipment.

They do not replace the external carrier's live tracking system.

Additional statuses require an approved specification.

---

# 9. Shipment Records

A shipment record may contain:

* Vehicle
* Customer
* Shipping company
* Container number
* Shipment status
* Shipment date
* Estimated arrival
* Tracking URL
* Internal notes
* Relevant timestamps

The final database fields must be defined in the approved data specification.

---

# 10. Customer Enquiries

Customers should be able to submit enquiries through appropriate website forms.

An enquiry may relate to:

* A specific vehicle.
* General vehicle availability.
* Purchase interest.
* Shipping questions.
* General company enquiries.

Forms must:

* Validate required information.
* Provide clear error feedback.
* Provide clear success feedback.
* Prevent obvious spam/abuse.
* Notify the appropriate internal recipient where configured.
* Avoid exposing internal information.

---

# 11. Team and Company Information

The website may display information about:

* CEOs
* Investors/Partners
* Managers
* Sales team
* Other employees

A team profile may contain:

* Professional photograph
* Name
* Position
* Short introduction

Only information approved by the company may be published.

---

# 12. Administrative Responsibility

Administrators are responsible for maintaining accurate business information.

The system should provide appropriate controls for:

* Vehicles
* Vehicle images
* Brands
* Shipments
* Enquiries
* Team members
* Website content
* Administrative settings

Administrative permissions must be enforced server-side.

---

# 13. Content Accuracy Rule

The application must never use fictional business data merely to make a page appear complete.

Examples of prohibited behavior:

* Fake vehicle listings in production.
* Fake employees.
* Fake customer enquiries.
* Fake container numbers.
* Fake shipment statuses.
* Fake prices.
* Fake company statistics.

Development/test data must be clearly separated from production data.

---

# 14. Change Request Rule

If a requested feature is not covered by the approved scope, it must follow this process:

**New Request**

→ **Scope Review**

→ **Business Impact Review**

→ **Architecture Impact Review**

→ **Security Impact Review**

→ **Approval**

→ **New/Updated Specification**

→ **Implementation**

→ **Testing**

No out-of-scope functionality may be introduced simply because an AI coding tool believes it would be useful.

---

# 15. Business Rule Priority

When making implementation decisions, use this priority:

1. Actual company business process
2. Approved project rules
3. Approved feature specification
4. Security requirements
5. User experience requirements
6. Technical implementation convenience

Technical convenience must never override an actual business rule.
