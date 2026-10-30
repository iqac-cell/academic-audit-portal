# MMMUT Internal Academic Audit Portal

A web portal for IQAC, Madan Mohan Malaviya University of Technology, Gorakhpur, to run the
**Internal Academic Audit (format Oct 2026)**:

* **Faculty** add their own records (papers, FDP/STTP, projects, patents, consultancy …). These go to the HOD.
* The **HOD** of each department fills department data in the exact tables of the audit format, uploads Annexures I–V and proofs, and submits.
* The portal **computes every indicator** (N1/N, CGPA, SE, HS, P, INT, NFD, FSM, Paper, AC, PSC …) and the **claimed marks** from the evaluation scheme.
* **IQAC** verifies each parameter (or returns the audit with remarks) and assigns **Auditor-1 and Auditor-2**.
* The auditors award marks. Final marks are the average of the two (configurable), and IQAC locks the audit.
* Reports: the full audit in the printed layout (HTML/PDF), an Excel workbook per department, and a university-wide parameter-wise summary.

Built with Laravel 12 (PHP 8.2+). It targets **MS SQL Server** and also runs on SQLite/MySQL for development.

## Why a module

The whole audit lives in `modules/AcademicAudit` (namespace `Mmmut\AcademicAudit`). The rest of this repository is only
a thin host app (login, password change). The module will later be moved into the main IQAC Laravel + MSSQL project,
and data that the IQAC project already holds will then prefill the audit tables. See [Merging into the IQAC project](#merging-into-the-iqac-project).

```
modules/AcademicAudit/
  config/academic-audit.php        route prefix, layout, user model, providers, uploads
  database/migrations/             all tables prefixed aa_  (MSSQL-safe)
  database/seeders/                departments (+ ERP spellings) and first cycle
  routes/web.php                   /academic-audit/...
  src/Support/Catalogue.php        ★ the audit format: 38 parameters, columns, formulas, default slabs
  src/Services/MetricCalculator    indicator formulas
  src/Services/SlabScorer          marks from slabs
  src/Services/ScoreService        claimed / awarded marks, section summary
  src/Services/WorkflowService     draft → submitted → returned → verified → audited → locked
  src/Services/ErpImporter         Samarth ERP employee export → faculty + logins
  src/Services/ReportBuilder       Excel reports
  src/Contracts/                   DirectoryProvider, PrefillProvider (integration points)
  resources/views/                 Blade + Bootstrap 5 (served locally, works offline on the intranet)
  tests/                           scoring boundary tests + end-to-end workflow tests
```

## Marks scheme

Max marks and slabs are seeded from the Evaluation Scheme (pages 16–19) and can be **edited by IQAC per cycle**
(Administration → Audit cycles → Marks scheme). A new cycle copies the previous cycle's scheme.

The printed format has inconsistencies that IQAC should confirm. The portal shows them on the marks-scheme page:

| Issue | Portal default |
|---|---|
| Section B = 200 in the Evaluation table, 350 in the Summary; Summary total 1000, but A+B+C+D = 950 | Uses the Evaluation Scheme: A 150, B 200, C 350, D 100 = **800** |
| A(i) 10 marks on the form, 15 in the scheme | 15 |
| A(f) max 15 and A(g) max 30, but the printed slabs stop at 10 | Printed slabs kept (top = 10) |
| Formulas for SE, Patent and Paper lack ×100 although the slabs are percentages | ×100 applied |
| Slab typos ("20 > PSC", "4 < NFD > 5", "2 < NC > 1", labels "Patent"/"FRA" under C.h/C.k) | Evident intent; noted on each parameter |
| FDP/STTP "min one week" | Programmes of ≥ 5 days are counted |

Denominators that the format does not define are entered by the HOD on the *Denominators & Yes/No items* page. These are:
graduating students as per sanctioned intake, eligible students for internship, faculty eligible for seed money,
PhD seats, and recognised guides. The number of regular faculty is taken from the faculty directory, and the HOD can override it.

## Roles and the role switcher

One person can hold several roles: Faculty, HOD (of a department), IQAC and Academic Auditor. They log in once and choose
the role to work in from the switcher at the top right. Every permission follows the **active role only**. For example, a
HOD who is also an auditor sees the departments assigned to them for audit only while working as Auditor. A faculty
record linked to the login counts as the Faculty role. IQAC adds roles to a person in Administration → Users & roles.

## Portal settings (IQAC → Administration → Portal settings)

| Tab | What it controls |
|---|---|
| General | Portal title, university name, contact email and notice on the login page |
| Workflow & uploads | Faculty contributions on/off, whether proof is required per row, upload size and types, auto-closing of cycles |
| Email (SMTP) | University SMTP or Gmail (use an App Password), plus a test-mail button |
| Login & password | "Forgot password" with an email OTP (digits, expiry, attempts), minimum password length, forced change for new accounts |
| Notifications | Emails on submit, return, verify, auditor assignment, and faculty row approval/rejection; deadline reminder days |
| AI assistance | Master switch and each AI feature, Claude API key, model, proxy, daily limit, connection test, usage table |

**Every new feature is off by default.** Secrets (SMTP password, API key) are stored encrypted with `APP_KEY`. If `APP_KEY`
changes, enter the secrets again.

### Daily task (reminders, auto-close)

Schedule once a day with Windows Task Scheduler:

```
php C:\sites\academic-audit-portal\artisan audit:remind
```

### AI assistance

| Feature | Where it runs | What it does |
|---|---|---|
| Data quality checks | Inside the portal (offline) | Flags duplicates, dates/years outside the academic year, CGPA > 10, N1 > N, placements > graduates, FDPs shorter than a week, names not in the faculty list, bad links/ISSN, missing proof and missing denominators. Shown on item pages, the overview, before submission and to IQAC. |
| Suggestions to gain marks | Inside the portal (offline) | For each parameter, explains the next slab of the evaluation scheme and what it needs ("1 more paper → +10"). |
| Previous-year prefill | Inside the portal (offline) | Copies basic information, memberships, running projects (without amounts), the mentor list and the citation faculty list from last year's audit, marked "previous". |
| Fill from document | Claude API (online) | The user uploads a certificate/paper/letter. The fields are read with Claude (structured output) and put in the form for the user to check before saving. The file becomes the row's proof. |

"Fill from document" needs an Anthropic API key and outbound HTTPS from the server to `api.anthropic.com` (or a proxy).
Uploaded documents are sent to Anthropic for processing. Use is logged in `aa_ai_logs` and limited per user per day.
Nothing an AI feature produces is saved or scored without the user confirming it.

## Installation (development)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # or configure sqlsrv in .env
php artisan migrate --seed              # departments, 2025-26 cycle, IQAC admin from IQAC_ADMIN_EMAIL
php artisan audit:import-erp /path/to/Samarth_ERP.xlsx
php artisan serve
```

The default password for created accounts is `ACADEMIC_AUDIT_DEFAULT_PASSWORD` (default `Mmmut@123`). Every account
created by the seeder, by the import or by IQAC must change its password at first login.

### Useful commands

```bash
php artisan audit:grant hod.cse@mmmut.ac.in hod --department=CSE      # make someone HOD
php artisan audit:grant someone@mmmut.ac.in auditor --name="Prof. X"  # academic auditor
php artisan audit:grant iqac2@mmmut.ac.in iqac
php artisan audit:new-cycle 2026-27                                    # next academic year
php artisan audit:import-erp Samarth_ERP.xlsx                          # safe to re-run
php artisan test
```

The same actions are available in the UI under **Administration**.

## Production on MS SQL Server

1. Install the Microsoft ODBC Driver 18 and the PHP `sqlsrv` / `pdo_sqlsrv` extensions (Windows: from the Microsoft
   Drivers for PHP for SQL Server; Linux: `pecl install sqlsrv pdo_sqlsrv`).
2. In `.env`, set `DB_CONNECTION=sqlsrv` and the `DB_*` values. If the server uses a self-signed
   certificate, set `DB_TRUST_SERVER_CERTIFICATE=true`.
3. Run `php artisan migrate --seed --force`, then `php artisan config:cache route:cache view:cache`.
4. Point IIS or Apache/Nginx at `public/`. Make `storage/` and `bootstrap/cache/` writable. Uploaded proofs and annexures
   are stored in `storage/app/private/academic-audit/` (not web-accessible; they are downloaded through permission checks).
5. Back up the database and `storage/app` together.

The schema avoids MSSQL pitfalls: no enum columns, a single cascade path per table, and JSON stored as `nvarchar(max)`.

## Hosting under another site's URL (IIS sub-application)

To serve the portal at `https://sdg.mmmut.ac.in/academic-audit` without copying it into the SDG folder:

1. In IIS Manager, right-click the SDG site and choose **Add Application**. Set Alias `academic-audit`, a separate application pool (No Managed Code),
   and Physical path `C:\sites\academic-audit-portal\public`.
2. In `.env`:
   ```
   APP_URL=https://sdg.mmmut.ac.in/academic-audit
   ACADEMIC_AUDIT_PREFIX=
   SESSION_COOKIE=academic_audit_session
   SESSION_PATH=/academic-audit
   ```
3. Run `php artisan config:cache` and `php artisan route:cache`.

`public/web.config` clears the parent site's rewrite rules, so SDG's own rules do not capture `/academic-audit/*` requests.

## Updating the server

```powershell
git pull
composer install --no-dev
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
Restart-WebAppPool -Name "AcademicAudit"
```

## Merging into the IQAC project

1. Copy `modules/AcademicAudit` into the IQAC project. Add these to its `composer.json` autoload:
   `"Mmmut\\AcademicAudit\\": "modules/AcademicAudit/src/"` and
   `"Mmmut\\AcademicAudit\\Database\\Seeders\\": "modules/AcademicAudit/database/seeders/"`, then run `composer dump-autoload`.
2. Register `Mmmut\AcademicAudit\AcademicAuditServiceProvider::class` in `bootstrap/providers.php` (or `config/app.php`).
3. `php artisan vendor:publish --tag=academic-audit-config` and set:
   * `user_model` → the IQAC user model, `middleware` → its auth middleware/guard
   * `layout` → the IQAC Blade layout (it must `@yield('content')` and `@stack('scripts')`)
   * `directory_provider` → a class implementing `Contracts\DirectoryProvider` that reads IQAC departments/faculty
   * `prefill_provider` → a class implementing `Contracts\PrefillProvider` that returns rows from IQAC tables
4. `php artisan migrate`. This creates only `aa_*` tables, so existing tables are not touched.
5. Copy the data of this standalone portal: export the `aa_*` tables and import them into the IQAC database. User ids must be
   remapped by email.

### Writing the IQAC prefill provider

`PrefillProvider::rows($code, $audit)` returns rows that use the column keys from `Support/Catalogue.php` for that parameter,
plus a stable `source_ref` (so a second prefill does not duplicate rows). Example for research papers (C.h):

```php
public function supports(): array { return ['C.h', 'C.p', 'C.d', 'A.g']; }

public function rows(string $code, Audit $audit): iterable
{
    [$from, $to] = $this->yearRange($audit->cycle->academic_year);   // 2025-07-01 .. 2026-06-30
    if ($code === 'C.h') {
        foreach (DB::table('publications')->where('department_id', $this->iqacDeptId($audit->department))
                     ->whereBetween('published_on', [$from, $to])->get() as $p) {
            yield ['source_ref' => "pub:{$p->id}", 'data' => [
                'title' => $p->title, 'faculty' => $p->authors, 'department' => $audit->department->name,
                'journal' => $p->journal, 'year' => substr($p->published_on, 0, 4), 'issn' => $p->issn, 'link' => $p->url,
            ]];
        }
    }
}
```

On every table page, HODs then see a **"Prefill from IQAC"** button. Prefilled rows are marked and can be edited or deleted
before submission.
