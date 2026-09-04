# Temporary Worker Management Portal

A lightweight web app for the **WEB08 — Temporary Worker Work, Attendance and Payment Record Portal** hackathon problem.

## Hackathon MVP

The app focuses on the required flow:
- Worker and employer record
- Work/task and date
- Daily or task-based agreed rate
- Attendance/record confirmation flow
- Overtime or extra work
- Advances and partial payments
- Pending amount calculation
- Dispute flag
- Complete record history
- **Print-Friendly Summary** challenge card

### Calculation

`Earned amount + approved extra work - advances - completed payments = pending amount`

## Important rule implemented

A confirmed record is treated as **locked** in the UI and confirmation is added to the record history. The app also exposes a dispute flag so a disagreement is visible rather than silently changing the record.

> This MVP is a record-keeping tool. It is not a payment service, payroll system, or legal dispute authority.

## Tech stack

- HTML
- CSS
- Vanilla JavaScript
- Browser LocalStorage
- No framework, build step, or external dependency

This makes the prototype extremely lightweight and easy to deploy on CyberPanel as a static website.

## Run locally

Open `index.html` in a browser, or serve the folder with any static web server.

## Deploy on CyberPanel

1. Create the website/domain `tezhack.aevix.xyz` in CyberPanel.
2. Open the website's document root / File Manager.
3. Upload `index.html`, `styles.css`, and `app.js`.
4. Make sure `index.html` is in the document root.
5. Enable SSL for the subdomain.
6. Open `https://tezhack.aevix.xyz`.

## GitHub

Suggested repository name:

`temporary-worker-management-portal`

Suggested commit sequence for the hackathon:

### Commit 1 — Initial MVP
- Added responsive dashboard
- Added work record creation
- Added rate and payment calculation
- Added record history

### Commit 2 — Confirmation & dispute flow
- Added record confirmation/lock state
- Added dispute flag
- Added role selector
- Added summary statistics

### Commit 3 — Challenge Card: Print-Friendly Summary
- Added dedicated print-friendly record summary
- Removed browser/app controls from printed output
- Added calculation breakdown and history to print view
- Tested print preview on a useful work record

## Suggested demo flow

1. Create a new worker work record.
2. Enter task, date, rate and units.
3. Add extra work, advance and partial payment.
4. Open the record and explain the calculation.
5. Confirm the record and show the locked state.
6. Flag a dispute and show the history.
7. Click **Print summary** and show the clean print preview.

## Future improvements

For a production version, replace LocalStorage with a shared backend/database and add real authentication, server-side authorization, append-only audit logs and proper multi-user synchronization.
