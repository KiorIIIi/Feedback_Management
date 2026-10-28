# CLSU Feedback Management System and Service Tracker

## Platform
web

## Stack
React, TypeScript, Vite; Laravel API; MySQL. Selected by the saved architecture and explicitly requested as the implementation baseline.

## Users and purpose
CLSU requesters submit and track their own service requests. MISO administrators review, clarify, prioritize, assign and report within their handling office. Personnel update assigned work. Visitors may submit independent general feedback.

## Constraints
This is a rough software version. Preserve the supplied Figma designs and original exported assets. The read-only MCP pilot now includes admin setup and ChatGPT OAuth; the latest 25 October instruction keeps data local and provider calls disabled. Operational writes remain deferred. Keep business rules in shared backend services.

## Prototype decisions
The user delegated draft policy decisions on 20 October 2026. Use configurable demo priority rules, explicit lifecycle transitions, one active assignee, a five-point evaluation scale matching Figma, optional requesting office, and calendar-day reports in Asia/Manila. These are not MISO-approved production policies.

## Evidence
docs/architecture/system-architecture.md, docs/architecture/database-design.md, docs/architecture/database-normalization.md; Figma file PeQN6nVBQbPNXWoTU32Mhm. Illustrative people and records must be identified as demo data.
