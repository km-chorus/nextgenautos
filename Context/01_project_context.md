# 01 — Project Context

## 1. Project Identity

## Mr. AmponsahAutos

This project is a professional vehicle-import website  and administrative platform for a company operating between Canada and Ghana.

The platform helps customers discover available vehicles, understand vehicle information, contact the company, understand the import/shipping process, and access official shipment tracking when tracking information becomes available.

Business lines (owner, 2026-10-14): vehicle imports from Canada to Ghana, and car parts for popular makes (for example Toyota, Honda, Hyundai). The website presents vehicles in detail; car parts are currently described in copy only (no parts catalogue: see `06_scope_and_business_rules.md`).

The website must represent the company's actual business operations. It must never invent business capabilities simply because a feature would be technically convenient.

---

## 2. Primary Markets

The primary markets are:

* Ghana
* Canada

The website must provide a professional and accessible experience on both mobile devices and desktop computers.

---

## 3. Project Goals

The platform should:

* Present available vehicles professionally.
* Make vehicle information easy to understand.
* Help customers make informed purchasing decisions.
* Clearly explain the vehicle import/shipping process.
* Provide access to the official external Ghana import-duty calculator.
* Provide access to official external shipment tracking.
* Build trust through professional company and team information.
* Provide clear contact and enquiry channels.
* Give authorized administrators a secure way to manage vehicles, shipments, enquiries, team information, brands, and website content.
* Be responsive, performant, secure, maintainable, and suitable for shared hosting.

---

## 4. Core Customer Journey

The intended customer journey is:

**Browse Vehicles**
→ **View Vehicle Details**
→ **Understand Specifications and Price**
→ **Check Ghana Import Duty**
→ **Contact / Purchase**
→ **Shipment Arranged**
→ **Container Number Provided**
→ **Company Communicates Container Number**
→ **Track Shipment**

The website acts as an information and access layer.

It is not the source of truth for customs duty or live carrier tracking.

---

## 5. Public Website

The planned public areas are:

* Home
* Vehicles / Inventory
* Vehicle Details
* Brand Browsing
* How It Works
* Track Your Vehicle
* About Us
* Our Team
* Contact Us
* FAQ

The exact page structure may be refined by later approved specifications.

---

## 6. Administrative Platform

The planned administrative areas are:

* Secure Login
* Dashboard
* Vehicle Management
* Shipment Management
* Enquiries
* Team Management
* Brand Management
* Website Content
* Settings / Administration

Only authorized users may access protected administrative functionality.

---

## 7. Supported Vehicle Brands

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

The architecture should allow additional brands to be added without redesigning the application.

---

## 8. Vehicle Information

Vehicle records may contain:

* Photos
* Year
* Make
* Model
* Trim
* Engine size
* Mileage
* Transmission
* Fuel type
* Drive type
* Key features/specifications
* Price
* Availability status
* Shipping information
* Duty-included status
* Description
* Other approved vehicle information

Only information provided through the approved data model or authorized administration should be displayed as factual vehicle information.

---

## 9. Technology Direction

The approved technology direction is:

* PHP 8.x
* MySQL
* PDO
* HTML5
* CSS3
* Vanilla JavaScript
* Bootstrap 5 or an approved custom CSS system
* Git/GitHub
* cPanel/shared hosting
* Brevo for project emails

The application will use a modular MVC-inspired PHP architecture.

React, Laravel, microservices, or another major architecture must not be introduced without explicit approval.

---

## 10. Hosting Direction

The application is intended to run on standard shared hosting with cPanel.

The architecture should therefore prioritize:

* Simple deployment
* Low infrastructure complexity
* PHP/MySQL compatibility
* Reasonable resource usage
* Maintainability
* Secure server-side processing

The project must not assume specialized infrastructure that has not been approved.

---

## 11. Project Principles

The project prioritizes:

* Professionalism
* Trust
* Simplicity
* Consistency
* Security
* Accuracy
* Performance
* Maintainability
* Responsive design
* Clear communication
* Real business processes

The website should feel like an established automotive/import company rather than a generic car marketplace, technology demo, or government portal.

---

## 12. Reality Over Features

The website must reflect what the company actually does.

For example:

* The website does not calculate Ghana import duty.
* The website does not generate container numbers.
* The website does not fabricate shipment statuses.
* The website does not claim live tracking without a real integration.
* The website does not pretend to have an external API integration that does not exist.

A new business capability requires an approved specification.

---

## 13. Terminology

### Vehicle

A vehicle record managed by the company and potentially displayed publicly.

### Customer

A person interested in purchasing, enquiring about, or tracking a vehicle.

### Shipment

A shipping record associated with a vehicle.

### Container Number

A real container identifier supplied by the shipping company.

### Shipping Company

The external company responsible for arranging/handling shipment and assigning the container number.

### Tracking Provider

The external service that provides shipment tracking information.

### Administrator

An authorized user who manages protected business information through the admin platform.

---

## 14. Foundation Rule

These six context files collectively form the permanent foundation of the project.

Feature specifications must follow these rules.

If a new request conflicts with an existing project rule, the conflict must be identified and resolved before implementation.
