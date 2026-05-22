Status: done

# Studio — Private Access Gate

## Problem Statement

The Studio is the private application where Producers process Videos. It does not yet exist as a deployable surface. Before any production functionality can be built, the Studio must be inaccessible to anyone who does not know the password — including casual URL discovery and automated crawlers.

## Solution

A password-protected entry point at `deaf.city/studio` that shows a blocker screen to unauthenticated visitors. A Producer enters the correct password, gains a session-scoped access token, and can then use the Studio. All Studio requests — present and future — are guaranteed to pass through a single auth check. Sessions expire after a configurable duration (default 24 hours). A logout action destroys the session and returns the Producer to the blocker screen.

## User Stories

1. As a Producer, I want to visit `deaf.city/studio` and see a password prompt, so that I know how to gain access to the Studio.
2. As a Producer, I want to submit the correct password and be let into the Studio immediately, so that I can start working without friction.
3. As a Producer, I want to submit a wrong password and see a generic error message, so that I know the attempt failed without leaking information.
4. As a Producer, I want my session to persist across page reloads without re-entering the password, so that I can work uninterrupted during a session.
5. As a Producer, I want my session to expire after the configured lifetime of inactivity, so that an unattended browser does not leave the Studio open indefinitely.
6. As a Producer, I want a logout action that returns me to the blocker screen, so that I can explicitly end my session on a shared machine.
7. As a Producer, I want every Studio action to silently verify my session before executing, so that I never accidentally perform an operation in an unauthenticated state.
8. As a Developer, I want the Studio password to be set in `config.php`, so that it is never committed to the repository.
9. As a Developer, I want the session lifetime to be configurable in `config.php`, so that it can be adjusted without touching application code.
10. As a Developer, I want all Studio requests to pass through a single entry point, so that it is structurally impossible to add a new Studio feature that accidentally bypasses auth.
11. As a Developer, I want the Studio to live in its own directory independent of the public Website, so that changes to either surface cannot accidentally affect the other.

## Implementation Decisions

### Studio directory structure

The Studio lives at `studio/` in the webroot — its own directory with its own entry point, independent of the public Website's `src/` app. This mirrors the existing `/preview/` pattern.

### Front controller

All requests to `studio/` are handled by a single `studio/index.php`. This file checks authentication first, then dispatches to the correct handler (login, logout, or the Studio shell). No Studio request can reach business logic without passing through this check.

### Auth Guard module

A dedicated Auth Guard encapsulates all session logic:
- `isAuthenticated()` — returns true if a valid, non-expired Studio session exists
- `login($password)` — verifies the submitted password against `STUDIO_PASSWORD`, creates the session and records the login timestamp on success, returns false on failure
- `logout()` — destroys the Studio session
- Session expiry is enforced on every `isAuthenticated()` call by comparing the stored login timestamp against `STUDIO_SESSION_LIFETIME`

The Auth Guard has no HTTP dependencies and can be exercised directly in tests.

### Password verification

The submitted password is compared directly against the `STUDIO_PASSWORD` constant from `config.php` using strict equality. No hashing. The security model relies on a strong password and the fact that the Studio URL is not publicly linked.

### Session lifetime

Session expiry is enforced at the application level — not via `session.gc_maxlifetime`. On login, the current timestamp is stored in the session. On every subsequent request, the Auth Guard checks whether `time() - session_login_time > STUDIO_SESSION_LIFETIME`. If expired, the session is destroyed and the Producer is redirected to the blocker.

`STUDIO_SESSION_LIFETIME` is defined in `config.php`. Default: `86400` (24 hours).

### Config additions

Two constants are added to `config.php`:
- `STUDIO_PASSWORD` — the plaintext Studio password
- `STUDIO_SESSION_LIFETIME` — session duration in seconds (default `86400`)

These constants must be defined before the Studio entry point is loaded.

### Blocker view

The blocker screen renders a full-page password form. It accepts an optional boolean flag to display a generic "Incorrect password" error message. The form POSTs to the same URL (`studio/`).

### Studio shell view

A placeholder page shown to authenticated Producers. Contains a title and a logout link. No other functionality in this iteration.

### No brute-force protection

No rate limiting or account lockout. The security model relies on a strong password and the Studio URL not being publicly discoverable.

## Testing Decisions

A good test verifies externally observable behaviour — what the module returns or what state it leaves behind — not how it achieves it internally.

The **Auth Guard** is the only module with enough logic to justify tests:

- A correct password creates an authenticated session
- An incorrect password does not create a session and returns false
- `isAuthenticated()` returns false before login
- `isAuthenticated()` returns true after a successful login
- `isAuthenticated()` returns false after the session lifetime has elapsed
- `logout()` causes `isAuthenticated()` to return false immediately after

The Front Controller, Blocker View, and Studio Shell View are shallow dispatch/template modules — no tests needed.

## Out of Scope

- Any Studio production functionality (video upload, subtitle editing, publication, etc.)
- Multiple Producer accounts or per-user passwords
- "Remember me" / persistent cookie authentication
- Brute-force rate limiting or IP-based lockout
- CSRF protection on the login form
- Password reset flow
- Audit logging of login events

## Further Notes

- `config.php` must never be read by agents or committed with real credentials. The file already exists at `config/config.php` and is gitignored. Add the two new constants there manually.
- The Studio session uses a distinct session variable namespace to avoid collisions with any future session use elsewhere on the site.
- When the Studio gains real functionality, all new action handlers must be added inside the front controller's authenticated branch — the guard is only as strong as the convention is followed.
