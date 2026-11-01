# Central login (Q-Sphere SSO) and Q-Sphere directory

The Academic Audit Portal signs people in through the Q-Sphere IQAC portal, which is the single login hub for
`https://sdg.mmmut.ac.in`. Q-Sphere is an OpenID Connect provider. The audit portal keeps its own roles
(`aa_user_roles`: iqac, hod, faculty, auditor) and its own data. People, departments and offices are mastered in Q-Sphere.

## How a login works

1. `/academic-audit/login` redirects to `/academic-audit/sso/login`. That page creates `state`, `nonce` and a PKCE
   verifier, keeps them in the session and redirects to `https://sdg.mmmut.ac.in/iqac/oauth/authorize`
   (`response_type=code`, `code_challenge_method=S256`, scope `openid email profile`).
2. Q-Sphere signs the person in and returns to `https://sdg.mmmut.ac.in/academic-audit/sso/callback?code=…&state=…`.
3. The portal checks `state`, exchanges the code at `/oauth/token` with the PKCE verifier and verifies the RS256
   `id_token` against `/oauth/jwks`: signature, `iss` = `SSO_ISSUER`, `aud` contains `SSO_CLIENT_ID`, `exp`, `nonce`.
   The JWKS is cached for an hour and fetched again when a token names an unknown `kid` (key rotation).
4. The user is matched by **email, lower-case**. The Q-Sphere `sub` is stored in `users.sso_sub` and the name is
   updated from the token.
   * Known user with a role (or a faculty record): logged in, the active role is cleared (the role switcher starts from
     the default role, as with the local login), and the session is marked as an SSO session. SSO users are not forced
     to change their local password.
   * Unknown user: an account is created **without** any `aa_user_roles` row, and the page "No access to the Academic
     Audit yet – ask the IQAC" is shown (HTTP 403, not logged in). IQAC then finds the person under
     Administration → Users & roles and gives a role.
5. Sign-in problems (wrong state, refused login, bad token) show "Sign-in failed" with "Try again" and
   "Emergency login (IQAC)".

Claims used from the `id_token`: `sub`, `email`, `name`, `sid` (optional). `employee_id`, `dept_code`, `office_code`
and `qs_roles` are accepted but not needed: roles in the audit portal come only from `aa_user_roles`.
If the `id_token` has no `email`, `/oauth/userinfo` is called with the access token.

## Emergency local login

`/academic-audit/login/local` keeps the old email + password form. With SSO on, it accepts **only users with the
`iqac` role**; everybody else gets "This login is only for IQAC administrators". "Forgot password" with the email OTP
still works for that path and returns to `/login/local`. With `SSO_ENABLED=false`, `/login` shows the form for everyone,
exactly as before.

## Logout

* **Logout button** (`POST /logout`): ends the local session and, for SSO sessions, redirects to
  `https://sdg.mmmut.ac.in/iqac/oauth/logout?post_logout_redirect_uri=https://sdg.mmmut.ac.in/academic-audit/&client_id=academic-audit`.
  Emergency (local) sessions return to `/login/local`.
* **Back-channel logout** (`POST /academic-audit/sso/backchannel-logout`, form field `logout_token`): Q-Sphere calls it
  server-to-server when someone signs out centrally. The token must be signed by Q-Sphere, have the right `iss`/`aud`,
  an `iat`, a `jti` (each is accepted once), the event `http://schemas.openid.net/event/backchannel-logout`, `sub`
  and/or `sid`, and no `nonce`. The portal then deletes that user's rows in `sessions` (needs `SESSION_DRIVER=database`)
  and clears the remember-me token. Answers 200, or 400 for an invalid token; never sets cookies. The route is outside
  the `web` middleware group (no session, no CSRF).

## What Q-Sphere must register for this client

| Setting | Value |
|---|---|
| client_id | `academic-audit` (or whatever `SSO_CLIENT_ID` says) |
| client type | confidential with secret (sent as `client_secret` in the token request body) or public + PKCE; PKCE S256 is always sent |
| redirect_uri | `https://sdg.mmmut.ac.in/academic-audit/sso/callback` |
| post_logout_redirect_uri | `https://sdg.mmmut.ac.in/academic-audit/` |
| backchannel_logout_uri | `https://sdg.mmmut.ac.in/academic-audit/sso/backchannel-logout` |
| scopes | `openid email profile` |
| Portal Home tile | `https://sdg.mmmut.ac.in/academic-audit/` |

## Directory from Q-Sphere

`ACADEMIC_AUDIT_DIRECTORY=qsphere` switches the module to `QSphereDirectoryProvider`
(`modules/AcademicAudit/src/Providers`). It reads, over the read-only connection `qsphere`:

* `sdg_departments(code, name, type, is_active)` – active rows only. Each code is matched to `aa_departments.short_name`
  (case-insensitive), else to an `aa_department_aliases.alias`, else by name (same loose matching as the ERP import).
  Codes that match nothing (offices, new departments) are ignored; add an alias in Administration → Departments to map
  a new code.
* `sdg_users(email, name, employee_id, dept_code, office_code, is_active[, designation])` – active rows of the matched
  codes. When a designation is given, only designations matching `ACADEMIC_AUDIT_FACULTY_DESIGNATIONS`
  (default `/professor|lecturer|teaching|faculty/i`) count as faculty.
* Local `aa_faculty` rows with the same email (from the ERP Excel import or entered by IQAC) add qualification,
  joining/retirement dates and the regular/contract flag. The regular-faculty count uses those (people without a local
  row count as regular).
* If Q-Sphere cannot be read, the local directory (ERP Excel import) is used and a warning is logged.
  `ACADEMIC_AUDIT_DIRECTORY=local` (default) keeps the old behaviour entirely.

## View for the Q-Sphere Portal Home

Migration `2026_11_01_000002_create_iqac_user_roles_view` creates view `iqac_user_roles(email, role, department)`:
`email` lower-case, `role` one of iqac/hod/faculty/auditor, `department` = `aa_departments.short_name` or NULL.
Active faculty records appear as role `faculty` (as the role switcher treats them). Q-Sphere shows e.g.
"Academic Audit: HOD (CSE)". Grant it with `deploy/server/grant-iqac-reader.sql`.

## Server settings (`C:\sites\academic-audit-portal\.env`)

```
APP_URL=https://sdg.mmmut.ac.in/academic-audit
ACADEMIC_AUDIT_PREFIX=
DB_CONNECTION=sqlsrv
DB_HOST=localhost
DB_PORT=14330
DB_DATABASE=academic_audit
SESSION_DRIVER=database
SESSION_COOKIE=academic_audit_session
SESSION_PATH=/academic-audit
SESSION_SECURE_COOKIE=true

SSO_ENABLED=true
SSO_ISSUER=https://sdg.mmmut.ac.in/iqac
SSO_CLIENT_ID=academic-audit
SSO_CLIENT_SECRET=            # only if Q-Sphere registers the client as confidential
# SSO_SERVER_URL=https://localhost/iqac   # only if the server cannot reach its own public name
# SSO_VERIFY_TLS=false                    # only together with SSO_SERVER_URL on a certificate without that name

ACADEMIC_AUDIT_DIRECTORY=qsphere
QSPHERE_DB_HOST=localhost
QSPHERE_DB_PORT=14330
QSPHERE_DB_DATABASE=qsphere
QSPHERE_DB_USERNAME=audit_reader
QSPHERE_DB_PASSWORD=          # set on the server only
QSPHERE_DB_TRUST_SERVER_CERTIFICATE=true
```

Then `php artisan migrate --force` and `php artisan config:cache route:cache` (or run
`deploy\server\update-from-git.ps1`).

SQL Server: `academic_audit`, `qsphere` and `sdg_portal` all live on the server's default instance MSSQLSERVER at
`tcp:localhost,14330` (so `DB_HOST=localhost`, `DB_PORT=14330` for the portal's own database too). Instance
MSSQLSERVER01 (port 1433) is not used by any of them; do not point anything at it.

1. On that instance: login `audit_reader` with SELECT on `qsphere.dbo.sdg_departments` and
   `qsphere.dbo.sdg_users` only (`deploy/server/grant-audit-reader-qsphere.sql`; Q-Sphere creates the views).
2. In `academic_audit`: user `iqac_reader` (the existing SDG reader login) with SELECT on `dbo.iqac_user_roles` only
   (`deploy/server/grant-iqac-reader.sql`). Run with `sqlcmd -S tcp:localhost,14330 -E -C -i grant-iqac-reader.sql`.

## Rolling out

1. Deploy with `SSO_ENABLED=false` first; run the migrations (adds `users.sso_sub`, `sso_sessions`, view
   `iqac_user_roles`). Nothing changes for users.
2. Register the client in Q-Sphere, fill the `SSO_*` settings, `SSO_ENABLED=true`, `php artisan config:cache`.
3. Test with an IQAC account at `/login/local` and with a HOD account through the central login.
4. Rollback: `SSO_ENABLED=false` + `php artisan config:cache` restores the local login for everyone.

## Tests

`tests/Feature/SsoLoginTest.php`, `BackchannelLogoutTest.php`, `LocalLoginTest.php`,
`QSphereDirectoryProviderTest.php`, `IqacUserRolesViewTest.php`. They use a fake Q-Sphere (`FakesQSphere`: HTTP fakes
for discovery, token and JWKS; tokens signed with the test-only keys in `tests/Fixtures`) and an in-memory SQLite
database standing in for the Q-Sphere views. Run `php artisan test`.
