# PHP runtime security update — 22 October 2026

## Advisory and scope

The [PHP security advisory published 21 October 2026](https://www.php.net/index.php) identifies CVE-2026-109618 and CVE-2026-109619 and lists PHP 8.5.12, 8.4.27, 8.3.36 and 8.2.35 as fixed releases. This update addresses those runtime vulnerabilities; it is not a claim that the entire application is vulnerability-free.

The MISO/Feedback Management application requires PHP 8.5. Use PHP 8.5.12 or a newer patched release in that branch. PHP 8.3.36 is installed only for the separate D-drive Laragon applications, not for this Laravel backend.

## Installed and selected runtimes

| Consumer | Previous runtime | Patched runtime |
| --- | --- | --- |
| C-drive Laragon and MISO local/public PHP services | 8.5.10 | 8.5.12, x64 thread-safe VS17 |
| D-drive Laragon applications | 8.3.30 | 8.3.36, x64 thread-safe VS16 |
| Default PHP command via refreshed Windows PATH | 8.1.10 | 8.5.12 |

Both Windows archives were downloaded from downloads.php.net and matched the SHA-256 values on the official PHP download pages before extraction. Existing php.ini settings were retained with extension paths changed to the new installations. Laragon selections, Apache module/FastCGI paths, the D-drive saved profile, and PHP download recipes were updated.

The old 8.1.10, 8.5.10 and 8.3.30 folders were moved out of Laragon's active bin/php folders into backup/retired-php-20261022 on their original drives. Their top-level executable files were renamed with a .disabled suffix. Do not restore them as a rollback: they are vulnerable. Configuration backups and downloaded archives are private local artifacts under .local/php-security-update-20261022 and are not committed.

Windows denied access to the machine-wide PATH registry setting. Its stale entries point to retired, absent paths. The user PATH points to the patched installation; resolving php with the persisted combined PATH selects only PHP 8.5.12. Reopen terminals after this update; an administrator may later remove the stale machine PATH entries.

## Branch and startup instructions

The front-main launch defaults and browser-test runtime path now select PHP 8.5.12. The main update is documentation-only; its older launch-script defaults must be overridden explicitly until the runtime code change is integrated. No UI changes were merged into main.

```powershell
$patchedPhp = 'C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe'
$env:PATH = (Split-Path $patchedPhp -Parent) + ';' + $env:PATH
& $patchedPhp -v
.\scripts\start.ps1 -PhpPath $patchedPhp
# If managing the existing public demo, supply the same override:
# .\scripts\stop-public.ps1 -PhpPath $patchedPhp
# .\scripts\start-public.ps1 -PhpPath $patchedPhp -UseWindowsDns
```

Do not run seeders or reset databases as part of this runtime update. The existing MISO API and public-host PHP processes were restarted on 8.5.12; the existing Cloudflare tunnel was left intact. Local and public-demo database configurations were preserved.

## Verification performed

These results were obtained on the development checkout, front-main, on 22 October 2026; they do not claim a separate main-branch application test run.

- PHP CLI reported 8.5.12 and 8.3.36 from the new installations.
- Composer check-platform-reqs passed against the installed MISO dependencies on PHP 8.5.12.
- The isolated SQLite backend suite passed: 60 tests, 446 assertions.
- Both Laragon Apache configuration checks returned Syntax OK.
- The local API /up on port 8000, frontend on port 5173, and public-host /up on port 4180 returned HTTP 200 after restart.
- Inspected running PHP processes used the new 8.5.12 executable; no inspected PHP process used a retired runtime.

Browser end-to-end tests and a fresh external public-URL test were not rerun for this runtime-only change. Historical verification records remain historical; earlier PHP 8.5.10 results are not evidence for the new runtime.
