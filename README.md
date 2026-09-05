# WorkerLedger — Temporary Worker Management Portal

A lightweight, database-backed web application for **WEB08 — Temporary Worker Work, Attendance and Payment Record Portal**.

WorkerLedger helps employers, supervisors and temporary workers maintain a shared, transparent record of work, attendance and payment information.

> **Important:** WorkerLedger is a record-keeping tool. It is not a payment service, payroll system, or legal dispute authority.

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
