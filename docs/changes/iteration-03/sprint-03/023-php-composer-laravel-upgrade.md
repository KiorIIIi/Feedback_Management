# PHP, Composer and Laravel upgrade

22 November 2026, Asia/Manila.

| Component | Before | After |
| --- | --- | --- |
| C-drive Laragon PHP | 8.5.12 | [8.6.0, x64 thread-safe VS18](https://www.php.net/releases/8.6/en.php) |
| Composer in Laragon | 2.4.1 | [2.11.2](https://getcomposer.org/download/) |
| Composer on the regular Windows PATH | 2.10.3 | 2.11.2 |
| Feedback Management backend | 12.73.0 | [13.43.0](https://github.com/laravel/framework/releases/tag/v13.43.0) |
| Global Laravel installer | 5.32.0 | [5.33.0](https://github.com/laravel/installer/releases/tag/v5.33.0) |

The published SHA-256 checksums matched the Windows PHP archive and the Composer executable. The previous PHP installation and backups of the Composer executables and Laragon configuration are available on disk. Laragon, its Apache configuration, the Windows user PATH and the project launchers select PHP 8.6.0. Reopen existing terminals to load the updated PATH.

The Laravel upgrade also updates PHPUnit to 12.5.45 and locks OAuth2 Server 9.5.0 and JWT 5.7.0, which add PHP 8.6 support. The bootstrap uses Laravel 13's request-forgery middleware configuration with the existing API exclusions. The backend keeps its PHP 8.5 minimum.

Apache configuration validation, Artisan startup, Composer platform checks on PHP 8.5.12 and 8.6.0, and the Windows launcher checks passed. Composer reported zero known advisories for the resolved dependencies.

On PHP 8.6, the isolated SQLite suite recorded 121 passes, 924 assertions and one skip for optional MySQL provisioning. The frontend production build passed. All 46 browser tests passed in the final isolated Chrome run. The dashboard CSV download timed out in the first full run and then passed three focused retries and the full rerun.

[Sprint overview](sprint-overview.md) · [Repository setup](../../../../README.md)
