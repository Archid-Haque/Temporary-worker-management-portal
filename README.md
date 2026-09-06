# WorkerLedger — Temporary Worker Management Portal

A lightweight, database-backed web application for **WEB08 — Temporary Worker Work, Attendance and Payment Record Portal**.

WorkerLedger helps employers, supervisors and temporary workers maintain a shared, transparent record of work, attendance and payment information.

> **Important:** WorkerLedger is a record-keeping tool. It is not a payment service, payroll system, or legal dispute authority.
> 
**Link-** tezhack.aevix.xyz
---

## Hackathon Problem

**WEB08 — Temporary Worker Work, Attendance and Payment Record Portal**

The system is designed around the required flow:

- Worker and employer records
- Work/task and date
- Daily or task-based agreed rate
- Attendance marking and worker confirmation
- Overtime / extra work
- Advances and partial payments
- Pending amount calculation
- Dispute flag
- Complete record and audit history
- Print-friendly work summary

---

## Core Calculation

```text
Earned amount + approved extra work
        - advances
        - completed payments
        = pending amount
```

---

## Key Features

### Work Records
- Create work records for temporary workers
- Store task, work date, rate type, rate and units/days
- Track extra work, advances and completed payments
- Automatically calculate pending amount

### Attendance & Confirmation
- Employer/supervisor can mark attendance
- Worker can confirm attendance
- Worker can raise a dispute with an optional note
- Attendance actions are recorded in history

### Record Integrity
- Pending records can be corrected before confirmation
- Pending records can be deleted by authorized users
- Confirmed records are locked
- Disputed records remain visible instead of being silently changed
- Important actions are recorded in the audit history

### Role-Based Access
The application supports four roles:

- **Admin** — manage users and system records
- **Employer** — create and manage worker records
- **Supervisor** — manage assigned work records
- **Worker** — review work, confirm attendance or raise disputes

### Admin Control Center
- Create user accounts
- Assign roles
- Activate/deactivate users
- Review recent records
- Review audit activity
- Customize portal branding
- Change the portal/site name
- Upload or remove the portal logo

### Global Branding
- Admin-controlled site name
- Custom logo upload and removal
- Branding applied across the landing page, login, dashboard and record pages
- When a logo is uploaded, the logo is displayed instead of the site name
- When no logo is configured, the site name is displayed
- Branding is also supported in print-friendly summaries

### Print-Friendly Summary
Each work record provides a clean **A4-ready print view** containing:

- Worker and employer details
- Work information
- Attendance status
- Payment calculation
- Pending amount
- Record status
- Recent audit history
- Portal branding

The summary provides a clean physical record of a work assignment and its payment status.

---

## Technical Architecture

The project started as a lightweight browser prototype and was later migrated to a server-backed architecture.

### Current Stack

- **Frontend:** HTML + CSS
- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Authentication:** PHP sessions + password hashing
- **Security:** CSRF protection + role-based authorization
- **Storage:** Server-side database
- **Deployment:** CyberPanel / OpenLiteSpeed

### Database

The current schema contains:

```text
users
work_records
attendance_events
payment_events
audit_logs
site_settings
```

The application stores user accounts, work records, attendance events, payment information, audit history and portal branding settings in the server database rather than browser LocalStorage.

---

## Security & Access Control

The current implementation includes:

- Password hashing and verification
- Session-based authentication
- Role-based authorization
- CSRF protection for state-changing actions
- Restricted access to work records
- Confirmation/locking of finalized records
- Audit entries for important record actions

Database credentials are kept in the server-side configuration and should **never be committed to GitHub**.

---

## Project Structure

```text
temporary-worker-management-portal/
│
├── README.md
├── .gitignore
│
├── app/
│   ├── auth.php
│   ├── config.php.example
│   ├── db.php
│   └── site.php
│
├── database/
│   └── schema.sql
│
└── public/
    ├── index.php
    ├── login.php
    ├── dashboard.php
    ├── admin.php
    ├── record.php
    ├── logout.php
    ├── setup.php
    └── assets/
        ├── style.css
        └── uploads/
```

### Configuration

Create the real server configuration from:

```text
app/config.php.example
```

The actual:

```text
app/config.php
```

contains environment-specific database credentials and should remain outside the public GitHub repository.

---

## Development Progress

### Day 1 — Prototype

The initial hackathon MVP was built as a lightweight client-side application.

Completed:

- Worker and employer record
- Work/task and date
- Daily or task-based rate
- Attendance/record confirmation flow
- Extra work
- Advances and partial payments
- Pending amount calculation
- Dispute flag
- Complete record history
- Print-friendly summary
- Lightweight HTML/CSS/JavaScript interface
- Browser LocalStorage for the initial prototype

The prototype was intentionally kept simple so the core workflow could be validated quickly.

### Day 2 — Backend Migration & Product Development

The prototype was evolved into a database-backed multi-user application.

Completed:

- Migrated persistent data from LocalStorage to MySQL/MariaDB
- Added PHP backend
- Added database schema and relationships
- Added server-side user accounts
- Added login and session authentication
- Added password hashing
- Added CSRF protection
- Added role-based access control
- Added Admin Control Center
- Added employer, supervisor and worker workflows
- Added server-stored work records
- Added attendance event history
- Added worker confirmation/dispute workflow
- Added pending record editing
- Added pending record deletion
- Added confirmed-record locking
- Added audit history
- Added print-friendly record summaries
- Added responsive UI and navigation
- Added footer and polished visual design
- Added global site branding
- Added admin-controlled site name
- Added admin logo upload and removal
- Applied branding consistently across the portal
- Added branded print-friendly summaries
- Deployed the application on CyberPanel/OpenLiteSpeed
- Connected the live application to MySQL/MariaDB
- Tested the multi-role workflow across devices/browsers

---

## Record History & Transparency

WorkerLedger maintains an audit trail for important actions so that changes are visible instead of being silently overwritten.

Examples of recorded events include:

```text
Record created
Record updated
Attendance marked
Attendance confirmed
Attendance disputed
Record confirmed & locked
Record deleted
User created
User status changed

---

## Demo Flow

A recommended hackathon demonstration:

1. Log in as an **Employer**
2. Create a temporary worker work record
3. Enter task, date, rate and units
4. Add extra work, advance and partial payment
5. Open the record and show the calculation
6. Mark attendance
7. Log in as the **Worker**
8. Confirm attendance or raise a dispute
9. Return to the record and show the shared history
10. Confirm and lock the record
11. Show that the finalized record cannot be silently edited
12. Open **Print Summary**
13. Show the Admin Control Center and audit history

---

## Deployment

The current application is deployed using **CyberPanel/OpenLiteSpeed** with PHP and MySQL/MariaDB.

For a server deployment:

1. Create the website/domain.
2. Configure the MySQL/MariaDB database and user.
3. Import `database/schema.sql`.
4. Configure the server-side `app/config.php`.
5. Point the website document root to the `public/` directory.
6. Upload the application files.
7. Enable HTTPS/SSL.
8. Create the initial administrator account using the setup process.
9. Disable/remove the setup endpoint after initial configuration.
10. Test each user role before production use.

---

## Product Direction

WorkerLedger is designed to move beyond a one-time hackathon prototype toward a practical company record-management product.

Potential production enhancements include:

- Individual payment transaction records
- Company/tenant isolation for multi-company SaaS
- Password reset and email verification
- Automated database backups
- Monitoring and rate limiting
- Stronger security review and automated tests
- Data retention and privacy controls
- Advanced reporting and filtering
- Exportable records and reports
- Notification system for pending confirmations/disputes

---

## GitHub Notes

Do **not** commit:

- Real database passwords
- `app/config.php`
- ZIP deployment packages
- Server logs
- Temporary/debug files

Use `app/config.php.example` as the safe configuration template.

---

## License

This project was developed as a hackathon project for the **WEB08 — Temporary Worker Work, Attendance and Payment Record Portal** problem statement.
